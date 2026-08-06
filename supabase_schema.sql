-- ============================================================
--  SafeMine TPKKP — Skema Backend Supabase
--  Project: qxaovlmbqbxfniftncie
--  Jalankan SELURUH skrip ini di Supabase → SQL Editor → Run.
--  Aman dijalankan ulang (idempoten).
-- ============================================================

create extension if not exists pgcrypto with schema extensions;

-- ---------- TABEL ----------
create table if not exists public.companies(
  id uuid primary key default gen_random_uuid(),
  name text not null,
  code text not null,
  jenis text, site text, komoditas text, ktt text,
  created_at timestamptz not null default now()
);

create table if not exists public.app_users(
  id uuid primary key default gen_random_uuid(),
  username text unique not null,
  password_hash text not null,
  name text not null,
  role text not null check (role in ('admin','company')),
  company_id uuid references public.companies(id) on delete set null,
  active boolean not null default true,
  created_at timestamptz not null default now(),
  last_login timestamptz
);

create table if not exists public.assessments(
  company_id uuid primary key references public.companies(id) on delete cascade,
  scores jsonb not null default '{}'::jsonb,
  profil jsonb not null default '{}'::jsonb,
  strata jsonb not null default '[]'::jsonb,
  programs jsonb not null default '{}'::jsonb,
  updated_at timestamptz not null default now()
);

create table if not exists public.kuesioner_responses(
  id text primary key,
  company_id uuid references public.companies(id) on delete cascade,
  cat text, nrp text, jabatan text, dept text, perusahaan text,
  answers jsonb not null default '{}'::jsonb,
  ts timestamptz not null default now()
);
create index if not exists idx_kr_company on public.kuesioner_responses(company_id);

create table if not exists public.activity_logs(
  id bigserial primary key,
  company_id uuid,
  user_id uuid,
  username text,
  action text, detail text,
  ts timestamptz not null default now()
);
create index if not exists idx_log_ts on public.activity_logs(ts desc);

create table if not exists public.app_settings(
  id int primary key default 1,
  prog_target int not null default 3,
  updated_at timestamptz not null default now()
);

create table if not exists public.sessions(
  token uuid primary key default gen_random_uuid(),
  user_id uuid references public.app_users(id) on delete cascade,
  role text, company_id uuid,
  created_at timestamptz not null default now(),
  expires_at timestamptz not null default now() + interval '30 days'
);
create index if not exists idx_sess_user on public.sessions(user_id);

-- ---------- KUNCI AKSES (RLS aktif, tanpa policy = akses langsung ditolak) ----------
alter table public.companies            enable row level security;
alter table public.app_users            enable row level security;
alter table public.assessments          enable row level security;
alter table public.kuesioner_responses  enable row level security;
alter table public.activity_logs        enable row level security;
alter table public.app_settings         enable row level security;
alter table public.sessions             enable row level security;
-- Tidak ada policy: seluruh akses HANYA lewat fungsi RPC (SECURITY DEFINER) di bawah.

-- ---------- HELPER: validasi sesi ----------
create or replace function public._sess(p_token uuid)
returns public.app_users language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users;
begin
  select au.* into u from public.app_users au
    join public.sessions s on s.user_id=au.id
   where s.token=p_token and s.expires_at>now() and au.active=true;
  if u.id is null then raise exception 'SESI_TIDAK_VALID'; end if;
  return u;
end;$$;

-- ---------- LOGIN / LOGOUT ----------
create or replace function public.app_login(p_username text, p_password text)
returns jsonb language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users; tok uuid;
begin
  select * into u from public.app_users where lower(username)=lower(p_username);
  if u.id is null then return jsonb_build_object('ok',false,'error','Nama pengguna tidak ditemukan'); end if;
  if not u.active then return jsonb_build_object('ok',false,'error','Akun dinonaktifkan. Hubungi administrator.'); end if;
  if u.password_hash <> extensions.crypt(p_password, u.password_hash) then
    return jsonb_build_object('ok',false,'error','Kata sandi salah'); end if;
  update public.app_users set last_login=now() where id=u.id;
  insert into public.sessions(user_id,role,company_id) values(u.id,u.role,u.company_id) returning token into tok;
  insert into public.activity_logs(company_id,user_id,username,action,detail) values(u.company_id,u.id,u.username,'Masuk','Pengguna '||u.username||' masuk');
  return jsonb_build_object('ok',true,'token',tok,
    'me',jsonb_build_object('id',u.id,'username',u.username,'name',u.name,'role',u.role,'companyId',u.company_id,'active',u.active,'created',u.created_at,'lastLogin',now()));
end;$$;

create or replace function public.app_logout(p_token uuid)
returns void language plpgsql security definer set search_path=public,extensions as $$
begin delete from public.sessions where token=p_token; end;$$;

-- ---------- BOOTSTRAP: kirim seluruh data sesuai peran ----------
create or replace function public.app_bootstrap(p_token uuid)
returns jsonb language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users; comp jsonb; usrs jsonb; data jsonb; logs jsonb; st jsonb;
begin
  u := public._sess(p_token);
  select jsonb_build_object('progTarget',coalesce(prog_target,3)) into st from public.app_settings where id=1;
  if st is null then st := jsonb_build_object('progTarget',3); end if;

  if u.role='admin' then
    select coalesce(jsonb_agg(jsonb_build_object('id',c.id,'name',c.name,'code',c.code,'jenis',c.jenis,'site',c.site,'komoditas',c.komoditas,'ktt',c.ktt,'created',c.created_at) order by c.created_at),'[]'::jsonb)
      into comp from public.companies c;
    select coalesce(jsonb_agg(jsonb_build_object('id',x.id,'username',x.username,'name',x.name,'role',x.role,'companyId',x.company_id,'active',x.active,'created',x.created_at,'lastLogin',x.last_login) order by x.created_at),'[]'::jsonb)
      into usrs from public.app_users x;
    select coalesce(jsonb_object_agg(c.id::text, jsonb_build_object(
        'scores',coalesce(a.scores,'{}'::jsonb),'profil',coalesce(a.profil,'{}'::jsonb),
        'strata',coalesce(a.strata,'[]'::jsonb),'programs',coalesce(a.programs,'{}'::jsonb),
        'responses',coalesce(r.arr,'[]'::jsonb))),'{}'::jsonb) into data
    from public.companies c
    left join public.assessments a on a.company_id=c.id
    left join lateral (select jsonb_agg(jsonb_build_object('id',k.id,'cat',k.cat,'nrp',k.nrp,'jabatan',k.jabatan,'dept',k.dept,'perusahaan',k.perusahaan,'answers',k.answers,'ts',k.ts) order by k.ts) arr
                       from public.kuesioner_responses k where k.company_id=c.id) r on true;
    select coalesce(jsonb_agg(jsonb_build_object('ts',l.ts,'userId',l.user_id,'username',l.username,'action',l.action,'detail',l.detail) order by l.ts desc),'[]'::jsonb)
      into logs from (select * from public.activity_logs order by ts desc limit 200) l;
  else
    select coalesce(jsonb_agg(jsonb_build_object('id',c.id,'name',c.name,'code',c.code,'jenis',c.jenis,'site',c.site,'komoditas',c.komoditas,'ktt',c.ktt,'created',c.created_at)),'[]'::jsonb)
      into comp from public.companies c where c.id=u.company_id;
    usrs := '[]'::jsonb;
    select coalesce(jsonb_object_agg(c.id::text, jsonb_build_object(
        'scores',coalesce(a.scores,'{}'::jsonb),'profil',coalesce(a.profil,'{}'::jsonb),
        'strata',coalesce(a.strata,'[]'::jsonb),'programs',coalesce(a.programs,'{}'::jsonb),
        'responses',coalesce(r.arr,'[]'::jsonb))),'{}'::jsonb) into data
    from public.companies c
    left join public.assessments a on a.company_id=c.id
    left join lateral (select jsonb_agg(jsonb_build_object('id',k.id,'cat',k.cat,'nrp',k.nrp,'jabatan',k.jabatan,'dept',k.dept,'perusahaan',k.perusahaan,'answers',k.answers,'ts',k.ts) order by k.ts) arr
                       from public.kuesioner_responses k where k.company_id=c.id) r on true
    where c.id=u.company_id;
    logs := '[]'::jsonb;
  end if;

  return jsonb_build_object('ok',true,
    'me',jsonb_build_object('id',u.id,'username',u.username,'name',u.name,'role',u.role,'companyId',u.company_id,'active',u.active,'created',u.created_at,'lastLogin',u.last_login),
    'db',jsonb_build_object('users',usrs,'companies',comp,'data',data,'logs',logs,'settings',st));
end;$$;

-- ---------- SIMPAN DATA PENILAIAN (scope per perusahaan) ----------
create or replace function public.app_save_company_data(p_token uuid, p_company_id uuid, p_scores jsonb, p_profil jsonb, p_strata jsonb, p_programs jsonb)
returns void language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users;
begin
  u := public._sess(p_token);
  if u.role<>'admin' and u.company_id<>p_company_id then raise exception 'AKSES_DITOLAK'; end if;
  insert into public.assessments(company_id,scores,profil,strata,programs,updated_at)
    values(p_company_id,coalesce(p_scores,'{}'),coalesce(p_profil,'{}'),coalesce(p_strata,'[]'),coalesce(p_programs,'{}'),now())
  on conflict (company_id) do update set scores=excluded.scores,profil=excluded.profil,strata=excluded.strata,programs=excluded.programs,updated_at=now();
end;$$;

-- ---------- SINKRON RESPONS KUESIONER (ganti-semua per perusahaan) ----------
create or replace function public.app_set_responses(p_token uuid, p_company_id uuid, p_responses jsonb)
returns void language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users; r jsonb;
begin
  u := public._sess(p_token);
  if u.role<>'admin' and u.company_id<>p_company_id then raise exception 'AKSES_DITOLAK'; end if;
  delete from public.kuesioner_responses where company_id=p_company_id;
  for r in select * from jsonb_array_elements(coalesce(p_responses,'[]'::jsonb)) loop
    insert into public.kuesioner_responses(id,company_id,cat,nrp,jabatan,dept,perusahaan,answers,ts)
    values(coalesce(r->>'id', gen_random_uuid()::text),p_company_id,r->>'cat',r->>'nrp',r->>'jabatan',r->>'dept',r->>'perusahaan',coalesce(r->'answers','{}'::jsonb),coalesce((r->>'ts')::timestamptz,now()))
    on conflict (id) do nothing;
  end loop;
end;$$;

-- ---------- LOG ----------
create or replace function public.app_log(p_token uuid, p_action text, p_detail text, p_company_id uuid)
returns void language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users;
begin
  u := public._sess(p_token);
  insert into public.activity_logs(company_id,user_id,username,action,detail) values(p_company_id,u.id,u.username,p_action,p_detail);
end;$$;

create or replace function public.app_clear_logs(p_token uuid)
returns void language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users;
begin
  u := public._sess(p_token);
  if u.role<>'admin' then raise exception 'AKSES_DITOLAK'; end if;
  delete from public.activity_logs;
  insert into public.activity_logs(user_id,username,action,detail) values(u.id,u.username,'Bersihkan Log','Log aktivitas dibersihkan');
end;$$;

-- ---------- PENGATURAN ----------
create or replace function public.app_set_setting(p_token uuid, p_prog_target int)
returns void language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users;
begin
  u := public._sess(p_token);
  if u.role<>'admin' then raise exception 'AKSES_DITOLAK'; end if;
  insert into public.app_settings(id,prog_target,updated_at) values(1,p_prog_target,now())
  on conflict (id) do update set prog_target=excluded.prog_target, updated_at=now();
end;$$;

-- ---------- GANTI SANDI SENDIRI ----------
create or replace function public.app_change_own_password(p_token uuid, p_newpass text)
returns void language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users;
begin
  u := public._sess(p_token);
  if char_length(p_newpass)<4 then raise exception 'SANDI_TERLALU_PENDEK'; end if;
  update public.app_users set password_hash=extensions.crypt(p_newpass, extensions.gen_salt('bf')) where id=u.id;
end;$$;

-- ---------- ADMIN: KELOLA PENGGUNA ----------
create or replace function public.app_upsert_user(p_token uuid, p_id uuid, p_username text, p_password text, p_name text, p_role text, p_company_id uuid)
returns void language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users; cnt int;
begin
  u := public._sess(p_token);
  if u.role<>'admin' then raise exception 'AKSES_DITOLAK'; end if;
  select count(*) into cnt from public.app_users where lower(username)=lower(p_username) and (p_id is null or id<>p_id);
  if cnt>0 then raise exception 'USERNAME_DIPAKAI'; end if;
  if p_id is null then
    insert into public.app_users(username,password_hash,name,role,company_id)
      values(p_username, extensions.crypt(coalesce(p_password,'changeme'), extensions.gen_salt('bf')), p_name, p_role, case when p_role='admin' then null else p_company_id end);
  else
    update public.app_users set username=p_username,name=p_name,role=p_role,
      company_id=case when p_role='admin' then null else p_company_id end,
      password_hash=case when p_password is null or p_password='' then password_hash else extensions.crypt(p_password, extensions.gen_salt('bf')) end
      where id=p_id;
  end if;
end;$$;

create or replace function public.app_delete_user(p_token uuid, p_id uuid)
returns void language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users;
begin
  u := public._sess(p_token);
  if u.role<>'admin' then raise exception 'AKSES_DITOLAK'; end if;
  if p_id=u.id then raise exception 'TIDAK_BISA_HAPUS_DIRI'; end if;
  delete from public.app_users where id=p_id;
end;$$;

create or replace function public.app_set_user_active(p_token uuid, p_id uuid, p_active boolean)
returns void language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users;
begin
  u := public._sess(p_token);
  if u.role<>'admin' then raise exception 'AKSES_DITOLAK'; end if;
  if p_id=u.id then raise exception 'TIDAK_BISA_NONAKTIF_DIRI'; end if;
  update public.app_users set active=p_active where id=p_id;
  if not p_active then delete from public.sessions where user_id=p_id; end if;
end;$$;

create or replace function public.app_reset_password(p_token uuid, p_id uuid, p_newpass text)
returns void language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users;
begin
  u := public._sess(p_token);
  if u.role<>'admin' then raise exception 'AKSES_DITOLAK'; end if;
  update public.app_users set password_hash=extensions.crypt(p_newpass, extensions.gen_salt('bf')) where id=p_id;
end;$$;

-- ---------- ADMIN: KELOLA PERUSAHAAN ----------
create or replace function public.app_upsert_company(p_token uuid, p_id uuid, p_name text, p_code text, p_jenis text, p_site text, p_komoditas text)
returns uuid language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users; newid uuid;
begin
  u := public._sess(p_token);
  if u.role<>'admin' then raise exception 'AKSES_DITOLAK'; end if;
  if p_id is null then
    insert into public.companies(name,code,jenis,site,komoditas,ktt) values(p_name,p_code,p_jenis,p_site,p_komoditas,'—') returning id into newid;
    insert into public.assessments(company_id,profil) values(newid, jsonb_build_object('nama',p_name,'jenis',p_jenis,'site',p_site)) on conflict do nothing;
    return newid;
  else
    update public.companies set name=p_name,code=p_code,jenis=p_jenis,site=p_site,komoditas=p_komoditas where id=p_id;
    return p_id;
  end if;
end;$$;

create or replace function public.app_delete_company(p_token uuid, p_id uuid)
returns void language plpgsql security definer set search_path=public,extensions as $$
declare u public.app_users; cnt int;
begin
  u := public._sess(p_token);
  if u.role<>'admin' then raise exception 'AKSES_DITOLAK'; end if;
  select count(*) into cnt from public.companies; if cnt<=1 then raise exception 'MIN_1_PERUSAHAAN'; end if;
  delete from public.app_users where company_id=p_id;
  delete from public.companies where id=p_id;  -- cascade hapus assessments & responses
end;$$;

-- ---------- HAK AKSES EKSEKUSI ----------
grant execute on function
  public.app_login(text,text), public.app_logout(uuid), public.app_bootstrap(uuid),
  public.app_save_company_data(uuid,uuid,jsonb,jsonb,jsonb,jsonb), public.app_set_responses(uuid,uuid,jsonb),
  public.app_log(uuid,text,text,uuid), public.app_clear_logs(uuid), public.app_set_setting(uuid,int),
  public.app_change_own_password(uuid,text),
  public.app_upsert_user(uuid,uuid,text,text,text,text,uuid), public.app_delete_user(uuid,uuid),
  public.app_set_user_active(uuid,uuid,boolean), public.app_reset_password(uuid,uuid,text),
  public.app_upsert_company(uuid,uuid,text,text,text,text,text), public.app_delete_company(uuid,uuid)
to anon, authenticated;

-- ---------- SEED (hanya jika belum ada pengguna) ----------
do $$
declare cdi uuid; bmu uuid;
begin
  if not exists (select 1 from public.app_users) then
    insert into public.companies(name,code,jenis,site,komoditas,ktt) values('PT Citra Dayak Indah','CDI','IUP OP Batubara','Kalimantan Tengah','Batubara','—') returning id into cdi;
    insert into public.companies(name,code,jenis,site,komoditas,ktt) values('PT Borneo Mandiri Utama','BMU','IUP OP Mineral & Batubara','Kalimantan Timur','Nikel','—') returning id into bmu;
    insert into public.assessments(company_id) values(cdi),(bmu) on conflict do nothing;
    insert into public.app_users(username,password_hash,name,role,company_id) values
      ('admin', extensions.crypt('admin123', extensions.gen_salt('bf')),'Administrator Sistem','admin',null),
      ('cdi',   extensions.crypt('cdi123',   extensions.gen_salt('bf')),'PJ Keselamatan CDI','company',cdi),
      ('borneo',extensions.crypt('borneo123',extensions.gen_salt('bf')),'PJ Keselamatan BMU','company',bmu);
    insert into public.app_settings(id,prog_target) values(1,3) on conflict (id) do nothing;
    insert into public.activity_logs(username,action,detail) values('system','Inisialisasi','Basis data SafeMine dibuat (2 perusahaan contoh)');
  end if;
end $$;

-- Selesai. Akun: admin/admin123 · cdi/cdi123 · borneo/borneo123
