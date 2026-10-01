-- Task search: a stored tsvector column against an expression index (ADR 0013).
-- Run in a throwaway database:
--   docker compose exec -T db psql -U todo_app -d postgres -c "create database fts_bench"
--   docker compose exec -T db psql -U todo_app -d fts_bench -f - < docs/benchmarks/task-search-vector-storage.sql
--   docker compose exec -T db psql -U todo_app -d postgres -c "drop database fts_bench"

\set ON_ERROR_STOP 1
\pset pager off
\timing off
select setseed(0.42);

-- ---------- data ----------
create table vocab as select array[
 'report','review','email','call','meeting','update','client','project','team','plan',
 'draft','send','check','prepare','schedule','budget','invoice','contract','design','release',
 'deploy','test','fix','refactor','document','database','server','backup','migration','index',
 'search','query','customer','payment','order','delivery','warehouse','supplier','meeting','agenda',
 'presentation','slides','quarter','forecast','hiring','interview','candidate','onboarding','training','workshop',
 'conference','travel','ticket','hotel','visa','insurance','doctor','dentist','pharmacy','gym',
 'grocery','laundry','cleaning','repair','plumber','electrician','garden','painting','furniture','kitchen',
 'birthday','anniversary','gift','party','dinner','restaurant','reservation','cinema','concert','museum',
 'library','book','article','course','lecture','homework','exam','thesis','research','experiment',
 'analysis','dashboard','metric','alert','incident','postmortem','security','audit','license','compliance',
 'tax','bank','loan','mortgage','rent','landlord','utility','internet','phone','subscription',
 'passport','renewal','registration','vehicle','mechanic','tyres','parking','charger','battery','laptop',
 'monitor','keyboard','printer','scanner','router','cable','adapter','headphones','camera','tripod',
 'newsletter','campaign','banner','landing','pricing','discount','coupon','refund','complaint','feedback',
 'survey','roadmap','backlog','sprint','retrospective','standup','estimate','deadline','milestone','launch'
]::text[] as w;

-- Zipf-like pick: a few words are very common, the tail is rare.
create function words(k int) returns text language sql volatile as $$
  select string_agg(w[1 + floor(array_length(w, 1) * power(random(), 3))::int], ' ')
  from vocab, generate_series(1, k)
$$;

-- 1,000,000 tasks: one power user (id 1) owns ~10%, 10,000 regular users ~90 each.
-- 0.1% of tasks carry the rare word "kubernetes".
create table src as
select uuidv7() as id,
       words(4) || case when random() < 0.001 then ' kubernetes' else '' end as title,
       words(30) as description,
       case when random() < 0.1 then 1 else 2 + floor(random() * 10000)::int end as user_id,
       now() - random() * interval '365 days' as created_at
from generate_series(1, 1000000);

-- Variant A: what the project has — stored generated column + GIN on it.
create table tasks_col (
  id uuid primary key, title varchar(255) not null, description text, user_id int not null,
  created_at timestamptz not null,
  search_vector tsvector generated always as (
    setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
    setweight(to_tsvector('english', coalesce(description, '')), 'B')) stored
);
-- Variant B: no column — GIN on the same expression.
create table tasks_expr (
  id uuid primary key, title varchar(255) not null, description text, user_id int not null,
  created_at timestamptz not null
);

insert into tasks_col  (id, title, description, user_id, created_at) select * from src;
insert into tasks_expr (id, title, description, user_id, created_at) select * from src;

create index on tasks_col  (user_id);
create index on tasks_expr (user_id);
create index tasks_col_gin  on tasks_col  using gin (search_vector);
create index tasks_expr_gin on tasks_expr using gin ((
    setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
    setweight(to_tsvector('english', coalesce(description, '')), 'B')));

vacuum analyze tasks_col;
vacuum analyze tasks_expr;

-- ---------- harness ----------
-- Runs the query n+1 times, drops the first (warm-up), returns the median execution time, ms.
create function bench(q text, n int default 9) returns numeric language plpgsql as $$
declare j json; t numeric[] := '{}';
begin
  for i in 1..n + 1 loop
    execute 'explain (analyze, format json) ' || q into j;
    if i > 1 then t := t || (j->0->>'Execution Time')::numeric; end if;
  end loop;
  return (select round(percentile_cont(0.5) within group (order by x)::numeric, 2) from unnest(t) x);
end $$;

-- The query the application sends, parameterised by where the vector comes from.
create function q(variant text, uid int, search text, ranked bool) returns text language sql as $$
  select replace(format(
    case when ranked then
      'select id, title, ts_rank_cd(VEC, websearch_to_tsquery(''english'', %2$L)) as r
         from %1$I where user_id = %3$s and VEC @@ websearch_to_tsquery(''english'', %2$L)
        order by r desc, id desc limit 20'
    else
      'select id, title from %1$I where user_id = %3$s
          and VEC @@ websearch_to_tsquery(''english'', %2$L)
        order by created_at desc, id desc limit 20'
    end, variant, search, uid),
    'VEC',
    case when variant = 'tasks_col' then 'search_vector' else
      '(setweight(to_tsvector(''english'', coalesce(title, '''')), ''A'') || setweight(to_tsvector(''english'', coalesce(description, '''')), ''B''))'
    end)
$$;

create table scenarios (n int, name text, uid int, search text, ranked bool);
insert into scenarios values
 (1, 'regular user, common word, ranked',      (select user_id from src where user_id > 1 group by 1 order by count(*) desc limit 1 offset 5000), 'budget', true),
 (2, 'power user, rare word, ranked',          1, 'kubernetes', true),
 (3, 'power user, common word, ranked',        1, 'budget', true),
 (4, 'power user, common word, NOT ranked',    1, 'budget', false),
 (5, 'power user, two words, ranked',          1, 'budget invoice', true),
 (6, 'power user, phrase, ranked',             1, '"budget invoice"', true),
 (7, 'power user, very common word, ranked',   1, 'report', true);

\echo
\echo === sizes ===
select c.relname, pg_size_pretty(pg_relation_size(c.oid)) as heap,
       pg_size_pretty(pg_table_size(c.oid)) as heap_with_toast,
       pg_size_pretty(pg_relation_size(i.oid)) as gin
from pg_class c join pg_class i on i.relname = c.relname || '_gin'
where c.relname in ('tasks_col', 'tasks_expr');

\echo
\echo === data shape ===
select (select count(*) from src where user_id = 1) as power_user_tasks,
       (select count(*) from src where user_id = (select uid from scenarios where n = 1)) as regular_user_tasks,
       (select round(avg(length(title))) from src) as avg_title, (select round(avg(length(description))) from src) as avg_descr;
select s.n, s.search,
       (select count(*) from tasks_col t where t.user_id = s.uid and t.search_vector @@ websearch_to_tsquery('english', s.search)) as matches
from scenarios s order by n;

\echo
\echo === read: median execution time, ms (9 runs after warm-up) ===
create table res as
select s.n, s.name, bench(q('tasks_col', s.uid, s.search, s.ranked)) as col_ms,
       bench(q('tasks_expr', s.uid, s.search, s.ranked)) as expr_ms
from scenarios s;
select n, name, col_ms, expr_ms, round(expr_ms / nullif(col_ms, 0), 1) as expr_slower_x from res order by n;

\echo
\echo === plans, scenario 3 (power user, common word, ranked) ===
select q('tasks_col', 1, 'budget', true) as qc, q('tasks_expr', 1, 'budget', true) as qe \gset
explain (analyze, buffers, costs off) :qc;
explain (analyze, buffers, costs off) :qe;
\echo === plans, scenario 1 (regular user) ===
select q('tasks_col', uid, search, true) as qc, q('tasks_expr', uid, search, true) as qe from scenarios where n = 1 \gset
explain (analyze, buffers, costs off) :qc;
explain (analyze, buffers, costs off) :qe;

-- ---------- combined index (the earlier open question) ----------
\echo
\echo === variant A + btree_gin (user_id, search_vector) ===
create extension btree_gin;
create index tasks_col_user_gin on tasks_col using gin (user_id, search_vector);
vacuum analyze tasks_col;
select pg_size_pretty(pg_relation_size('tasks_col_user_gin')) as combined_gin_size;
select s.n, s.name, r.col_ms as before_ms, bench(q('tasks_col', s.uid, s.search, s.ranked)) as with_combined_ms
from scenarios s join res r using (n) order by n;
select q('tasks_col', 1, 'budget', true) as qc \gset
explain (analyze, buffers, costs off) :qc;
drop index tasks_col_user_gin;

-- ---------- write cost ----------
\echo
\echo === write: 50,000 inserts, then 50,000 title updates, ms ===
create table extra as
select uuidv7() as id, words(4) as title, words(30) as description,
       2 + floor(random() * 10000)::int as user_id, now() as created_at
from generate_series(1, 50000);

create function timed(sql text) returns numeric language plpgsql as $$
declare t0 timestamptz := clock_timestamp();
begin execute sql; return round(extract(epoch from clock_timestamp() - t0) * 1000); end $$;

select timed('insert into tasks_col (id, title, description, user_id, created_at) select * from extra')  as col_insert_ms,
       timed('insert into tasks_expr (id, title, description, user_id, created_at) select * from extra') as expr_insert_ms;
select timed('update tasks_col  set title = title || '' urgent'' where id in (select id from extra)') as col_update_ms,
       timed('update tasks_expr set title = title || '' urgent'' where id in (select id from extra)') as expr_update_ms;
-- An update that does not touch the indexed text (status change, due date...).
select timed('update tasks_col  set created_at = now() where id in (select id from extra)') as col_update_other_ms,
       timed('update tasks_expr set created_at = now() where id in (select id from extra)') as expr_update_other_ms;
