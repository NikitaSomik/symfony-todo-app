# 0013. Search tasks with PostgreSQL full-text search over a stored, generated `tsvector`

- Status: accepted
- Date: 2026-03-27
- Implemented in: #8, #60, #61, #62

## Context

Task search started as `LOWER(title) LIKE '%term%' OR LOWER(description) LIKE '%term%'`.
That is substring matching: no ranking, no weighting of the title over the description,
no handling of several words, and a leading `%` keeps a B-tree index from being used.

## Decision

- **Column.** `tasks.search_vector` is `GENERATED ALWAYS AS (...) STORED`: the title with
  weight `A`, the description with weight `B`, indexed with GIN. PostgreSQL keeps it up
  to date; PHP never writes it.
- **Language.** The `english` configuration (#60): words are reduced to their stem and
  stop words are dropped, so `task` finds "Review tasks". `Task::SEARCH_CONFIG` holds the
  name for the column and the queries alike.
- **Query.** `websearch_to_tsquery` parses the user's input and `ts_rank_cd` ranks the
  matches, both through DQL functions from `martin-georgiev/postgresql-for-doctrine`.
- **Order** (#62). Without a chosen `sort`, a search is ordered by relevance. A chosen
  `sort` replaces it; relevance then breaks its ties, best match first.
- **Mapping** (#61). The column is mapped as `insertable: false, updatable: false`, with
  the generation expression in `columnDefinition`, and is deliberately not marked
  `generated`.

The API contract did not change: the search stays one parameter, `filter[search]`.

## Alternatives considered

- **Keep `LIKE`.** See the context.
- **An external search engine.** Too early: it brings another service to run and data to
  keep in sync with the database, for a search over two columns of one table.
- **A trigger that maintains the `tsvector`.** The PostgreSQL documentation presents
  triggers as the older approach to what stored generated columns now do, and a trigger
  is extra machinery: it hides logic that the column definition states in the schema.
- **An expression index on `to_tsvector(...)`, without a column.** A real option: the
  index lookup is as fast, the schema is simpler and the table is less than half the size
  (311 MB against 701 MB in the measurement below). It loses on what the index cannot
  answer. GIN stores neither positions nor weights, so `ts_rank_cd` needs the vector
  itself — and without a column PostgreSQL parses and stems the text again for every
  matched row, on every query. The same happens whenever the planner reaches the rows
  through `idx_tasks_user_id` and checks the match row by row, which is what it does for
  an ordinary user. Measured: 0.19 ms against 3.8 ms for a user with 90 tasks. Without
  ranking the expression index was the faster one, so the column is worth its size only
  while results are ordered by relevance.
- **A combined GIN index on `(user_id, search_vector)` (`btree_gin`).** Measured and not
  added: it speeds up rare words and phrases for a user with 100,000 tasks, slows the
  ordinary user down (0.19 ms to 1.4 ms) and does nothing for the slow case, where the
  time goes into ranking the matches rather than finding them.
- **The `simple` configuration.** The first version. It matches a word only in the form
  it was written: `task` did not find "tasks", and `call the bank` required `the`.
- **`to_tsquery` for the user's input.** It raises a syntax error on input that contains
  query operators; `websearch_to_tsquery` never does. A test in #60 covers such input.
- **`generated: 'ALWAYS'` in the Doctrine mapping.** Doctrine then reads the column back
  after every write, and its change detection does not skip a column that is not
  updatable (doctrine/orm#12017, still open): the task looks changed, and the next flush
  sends an extra `UPDATE`. Removed in #61; the property now goes stale after a write,
  which is harmless because PHP never reads it.
- **Relevance always first.** The behaviour until #62: `ts_rank_cd` returns a float that
  rarely ties, so a chosen `sort` had no visible effect on a search.
- **`relevance` as a `sort` value.** Leaving `sort` out already means it. Adding the value
  later is backward compatible; removing it would not be.

## Consequences

- The search is tied to PostgreSQL, and every `INSERT` and `UPDATE` of a task also
  maintains the vector and the GIN index.
- The expression exists in two places — the migration and `columnDefinition` — and must
  stay identical, or `doctrine:schema:validate` reports a difference. Changing it means a
  migration that rewrites the column.
- `english` has a price: a query made only of stop words (`the`, `to do`) finds nothing,
  and stemming joins different words — `universe` matches "University". Task text in
  another language is not stemmed correctly.
- No typo tolerance (`pg_trgm`), no accent folding (`unaccent`), no highlighted snippets
  (`ts_headline`). Each is a possible next step, none was needed yet.
- Every search is also filtered by the owner. Measured on PostgreSQL 18 with a million
  tasks ([script](../benchmarks/task-search-vector-storage.sql)): for a user with 90
  tasks the planner reads them through `idx_tasks_user_id` and checks the stored vector
  row by row — the GIN index is not used at all. It starts to matter only for a user with
  far more tasks than that.
- Since #78 the filter is the workspace, not the owner
  ([0020](0020-a-task-belongs-to-a-workspace.md)), and the index is
  `idx_tasks_workspace_id`. The numbers here are per number of tasks behind that filter
  and read the same way for a workspace.
- Relevance order has a ceiling. To return the 20 best matches PostgreSQL ranks every
  match: about 300 ms when one user has 29,000 matching tasks. No index here removes
  that. It is the signal to cap the ranked set, or to move to an index that stores
  positions (RUM) or to a search engine.
- The stored vector is larger than the text it is built from: the table more than doubles.
  If relevance order is ever dropped, the column stops paying for itself and the
  expression index becomes the better choice.
