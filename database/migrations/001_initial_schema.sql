begin;

create table if not exists schema_migrations (
    version text primary key,
    applied_at timestamptz not null default now()
);

create type severity_level as enum ('LOW', 'MEDIUM', 'HIGH');
create type recommended_action as enum (
    'ROUTINE_CARE_OR_SELF_CARE',
    'SEEK_CARE_SOON',
    'SEEK_EMERGENCY_CARE_NOW'
);
create type hospital_capability as enum ('CLINIC_ONLY', 'ER_CAPABLE');
create type routing_mode as enum ('AUTO', 'MANUAL');
create type staff_role as enum ('TRIAGE', 'DOCTOR', 'HOSPITAL_ADMIN', 'SUPER_ADMIN');
create type filling_as as enum ('SELF', 'COMPANION', 'CAREGIVER');
create type visit_status as enum (
    'DRAFT',
    'ASSESSING',
    'ASSESSMENT_COMPLETE',
    'SUBMITTED',
    'CANCELLED'
);
create type assessment_status as enum ('PENDING', 'PROCESSING', 'COMPLETE', 'FAILED');
create type submission_status as enum ('RECEIVED', 'ACKNOWLEDGED', 'IN_TRIAGE', 'CLOSED');
create type arrival_type as enum ('PRE_ARRIVAL', 'WALK_IN_QR');
create type consent_type as enum (
    'AI_DISCLAIMER',
    'PRIVACY_NOTICE',
    'LOCATION_ACCESS',
    'FITBIT_ACCESS',
    'HOSPITAL_SHARE'
);
create type staff_availability_status as enum ('AVAILABLE', 'BUSY', 'OFFLINE');
create type sex_at_birth as enum ('FEMALE', 'MALE', 'INTERSEX', 'UNKNOWN', 'PREFER_NOT_TO_SAY');
create type pregnancy_status as enum ('PREGNANT', 'NOT_PREGNANT', 'UNKNOWN', 'NOT_APPLICABLE');

create table hospitals (
    id uuid primary key default gen_random_uuid(),
    slug text not null unique,
    name text not null,
    capability hospital_capability not null,
    routing_mode routing_mode not null default 'MANUAL',
    accepting_submissions boolean not null default true,
    is_active boolean not null default true,
    brand_name text,
    brand_color text,
    logo_url text,
    address text,
    latitude numeric(9, 6),
    longitude numeric(9, 6),
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint hospitals_slug_format check (slug ~ '^[a-z0-9]+(?:-[a-z0-9]+)*$'),
    constraint hospitals_brand_color_format check (
        brand_color is null or brand_color ~ '^#[0-9A-Fa-f]{6}$'
    ),
    constraint hospitals_latitude_range check (
        latitude is null or latitude between -90 and 90
    ),
    constraint hospitals_longitude_range check (
        longitude is null or longitude between -180 and 180
    )
);

create table staff_users (
    id uuid primary key default gen_random_uuid(),
    hospital_id uuid references hospitals(id) on delete restrict,
    email text not null,
    password_hash text not null,
    first_name text not null,
    last_name text not null,
    role staff_role not null,
    is_active boolean not null default true,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint staff_users_email_not_blank check (btrim(email) <> ''),
    constraint staff_users_password_hash_not_blank check (btrim(password_hash) <> ''),
    constraint staff_users_hospital_scope check (
        (role = 'SUPER_ADMIN' and hospital_id is null)
        or (role <> 'SUPER_ADMIN' and hospital_id is not null)
    ),
    unique (id, hospital_id)
);

create unique index staff_users_email_unique_ci on staff_users (lower(email));
create index staff_users_hospital_id_idx on staff_users (hospital_id);

create table patients (
    id uuid primary key default gen_random_uuid(),
    normalized_phone text not null unique,
    first_name text not null,
    last_name text not null,
    date_of_birth date,
    sex_at_birth sex_at_birth not null default 'UNKNOWN',
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint patients_phone_format check (normalized_phone ~ '^\+[1-9][0-9]{7,14}$'),
    constraint patients_first_name_not_blank check (btrim(first_name) <> ''),
    constraint patients_last_name_not_blank check (btrim(last_name) <> '')
);

create table consultant_requests (
    id uuid primary key default gen_random_uuid(),
    name text not null,
    normalized_phone text not null,
    concern text,
    status text not null default 'NEW',
    phone_verified_at timestamptz,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint consultant_requests_name_not_blank check (btrim(name) <> ''),
    constraint consultant_requests_phone_format check (normalized_phone ~ '^\+[1-9][0-9]{7,14}$'),
    constraint consultant_requests_status_valid check (status in ('NEW', 'CONTACTED', 'CLOSED'))
);

create index consultant_requests_status_created_at_idx on consultant_requests (status, created_at);

create table patient_conditions (
    id uuid primary key default gen_random_uuid(),
    patient_id uuid not null references patients(id) on delete cascade,
    name text not null,
    is_active boolean not null default true,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint patient_conditions_name_not_blank check (btrim(name) <> ''),
    unique (patient_id, name)
);

create table patient_allergies (
    id uuid primary key default gen_random_uuid(),
    patient_id uuid not null references patients(id) on delete cascade,
    name text not null,
    is_active boolean not null default true,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint patient_allergies_name_not_blank check (btrim(name) <> ''),
    unique (patient_id, name)
);

create table patient_medications (
    id uuid primary key default gen_random_uuid(),
    patient_id uuid not null references patients(id) on delete cascade,
    name text not null,
    dose text,
    frequency text,
    is_active boolean not null default true,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint patient_medications_name_not_blank check (btrim(name) <> '')
);

create index patient_conditions_patient_id_idx on patient_conditions (patient_id);
create index patient_allergies_patient_id_idx on patient_allergies (patient_id);
create index patient_medications_patient_id_idx on patient_medications (patient_id);

create table visits (
    id uuid primary key default gen_random_uuid(),
    patient_id uuid not null references patients(id) on delete restrict,
    preselected_hospital_id uuid references hospitals(id) on delete set null,
    status visit_status not null default 'DRAFT',
    filling_as filling_as not null,
    respondent_name text,
    relationship_to_patient text,
    age_years smallint not null,
    sex_at_birth sex_at_birth not null default 'UNKNOWN',
    pregnancy_status pregnancy_status not null default 'NOT_APPLICABLE',
    intake_answers jsonb not null default '{}'::jsonb,
    manual_vitals jsonb,
    wearable_vitals jsonb,
    latitude numeric(9, 6),
    longitude numeric(9, 6),
    manual_location text,
    started_at timestamptz not null default now(),
    completed_at timestamptz,
    updated_at timestamptz not null default now(),
    unique (id, patient_id),
    constraint visits_age_range check (age_years between 0 and 120),
    constraint visits_respondent_required check (
        filling_as = 'SELF'
        or (
            nullif(btrim(respondent_name), '') is not null
            and nullif(btrim(relationship_to_patient), '') is not null
        )
    ),
    constraint visits_answers_object check (jsonb_typeof(intake_answers) = 'object'),
    constraint visits_manual_vitals_object check (
        manual_vitals is null or jsonb_typeof(manual_vitals) = 'object'
    ),
    constraint visits_wearable_vitals_object check (
        wearable_vitals is null or jsonb_typeof(wearable_vitals) = 'object'
    ),
    constraint visits_latitude_range check (latitude is null or latitude between -90 and 90),
    constraint visits_longitude_range check (longitude is null or longitude between -180 and 180)
);

create index visits_patient_started_idx on visits (patient_id, started_at desc);
create index visits_status_idx on visits (status);

create table assessments (
    id uuid primary key default gen_random_uuid(),
    visit_id uuid not null unique references visits(id) on delete cascade,
    status assessment_status not null default 'PENDING',
    severity severity_level,
    recommended_action recommended_action,
    symptom_summary text,
    red_flags jsonb not null default '[]'::jsonb,
    reason_codes jsonb not null default '[]'::jsonb,
    deterministic_red_flag_match boolean not null default false,
    ai_schema_version text not null default '1.0',
    provider_model text,
    question_count smallint not null default 0,
    error_code text,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now(),
    constraint assessments_red_flags_array check (jsonb_typeof(red_flags) = 'array'),
    constraint assessments_reason_codes_array check (jsonb_typeof(reason_codes) = 'array'),
    constraint assessments_question_count_range check (question_count between 0 and 12),
    constraint assessments_red_flag_is_high check (
        not deterministic_red_flag_match or severity = 'HIGH'
    ),
    constraint assessments_complete_fields check (
        status <> 'COMPLETE'
        or (
            severity is not null
            and recommended_action is not null
            and nullif(btrim(symptom_summary), '') is not null
        )
    ),
    constraint assessments_summary_length check (
        symptom_summary is null or char_length(symptom_summary) between 1 and 1500
    ),
    constraint assessments_severity_action_mapping check (
        severity is null
        or recommended_action is null
        or (severity = 'HIGH' and recommended_action = 'SEEK_EMERGENCY_CARE_NOW')
        or (severity = 'MEDIUM' and recommended_action = 'SEEK_CARE_SOON')
        or (severity = 'LOW' and recommended_action = 'ROUTINE_CARE_OR_SELF_CARE')
    )
);

create index assessments_severity_idx on assessments (severity) where status = 'COMPLETE';

create table consent_events (
    id uuid primary key default gen_random_uuid(),
    patient_id uuid not null,
    visit_id uuid not null,
    hospital_id uuid references hospitals(id) on delete restrict,
    consent_type consent_type not null,
    consent_version text not null,
    granted boolean not null,
    recorded_at timestamptz not null default now(),
    constraint consent_events_hospital_share_target check (
        consent_type <> 'HOSPITAL_SHARE' or hospital_id is not null
    ),
    foreign key (visit_id, patient_id)
        references visits(id, patient_id)
        on delete cascade
);

create index consent_events_visit_idx on consent_events (visit_id, recorded_at desc);

create table hospital_submissions (
    id uuid primary key default gen_random_uuid(),
    visit_id uuid not null unique references visits(id) on delete restrict,
    hospital_id uuid not null references hospitals(id) on delete restrict,
    consent_event_id uuid not null unique references consent_events(id) on delete restrict,
    status submission_status not null default 'RECEIVED',
    arrival_type arrival_type not null,
    severity severity_level not null,
    recommended_action recommended_action not null,
    submission_snapshot jsonb not null,
    received_at timestamptz not null default now(),
    acknowledged_at timestamptz,
    closed_at timestamptz,
    updated_at timestamptz not null default now(),
    constraint hospital_submissions_snapshot_object check (
        jsonb_typeof(submission_snapshot) = 'object'
    ),
    constraint hospital_submissions_severity_action_mapping check (
        (severity = 'HIGH' and recommended_action = 'SEEK_EMERGENCY_CARE_NOW')
        or (severity = 'MEDIUM' and recommended_action = 'SEEK_CARE_SOON')
        or (severity = 'LOW' and recommended_action = 'ROUTINE_CARE_OR_SELF_CARE')
    )
);

create index hospital_submissions_queue_idx
    on hospital_submissions (hospital_id, status, received_at desc);
create index hospital_submissions_severity_idx
    on hospital_submissions (hospital_id, severity, received_at desc);

create table staff_availability (
    id uuid primary key default gen_random_uuid(),
    staff_user_id uuid not null,
    hospital_id uuid not null,
    status staff_availability_status not null default 'OFFLINE',
    updated_at timestamptz not null default now(),
    unique (staff_user_id),
    foreign key (staff_user_id, hospital_id)
        references staff_users(id, hospital_id)
        on delete cascade
);

create index staff_availability_hospital_status_idx
    on staff_availability (hospital_id, status);

create table audit_events (
    id bigint generated always as identity primary key,
    hospital_id uuid references hospitals(id) on delete set null,
    actor_type text not null,
    actor_id uuid,
    action text not null,
    entity_type text not null,
    entity_id uuid,
    metadata jsonb not null default '{}'::jsonb,
    created_at timestamptz not null default now(),
    constraint audit_events_actor_type check (
        actor_type in ('PATIENT', 'STAFF', 'SUPER_ADMIN', 'SYSTEM')
    ),
    constraint audit_events_action_not_blank check (btrim(action) <> ''),
    constraint audit_events_entity_type_not_blank check (btrim(entity_type) <> ''),
    constraint audit_events_metadata_object check (jsonb_typeof(metadata) = 'object')
);

create index audit_events_hospital_created_idx
    on audit_events (hospital_id, created_at desc);
create index audit_events_entity_idx
    on audit_events (entity_type, entity_id, created_at desc);

create table ai_request_events (
    id bigint generated always as identity primary key,
    feature text not null,
    provider text not null,
    model text not null,
    outcome text not null,
    duration_ms integer not null,
    attempt_count smallint not null,
    retry_count smallint not null,
    error_code text,
    created_at timestamptz not null default now(),
    constraint ai_request_events_outcome_valid check (outcome in ('SUCCEEDED', 'FAILED')),
    constraint ai_request_events_duration_valid check (duration_ms >= 0),
    constraint ai_request_events_attempt_count_valid check (attempt_count between 0 and 10),
    constraint ai_request_events_retry_count_valid check (retry_count between 0 and 9)
);

create index ai_request_events_feature_created_at_idx
    on ai_request_events (feature, created_at desc);
create index ai_request_events_outcome_created_at_idx
    on ai_request_events (outcome, created_at desc);

create or replace function set_updated_at()
returns trigger
language plpgsql
as $$
begin
    new.updated_at = now();
    return new;
end;
$$;

create trigger hospitals_set_updated_at
before update on hospitals
for each row execute function set_updated_at();

create trigger staff_users_set_updated_at
before update on staff_users
for each row execute function set_updated_at();

create trigger patients_set_updated_at
before update on patients
for each row execute function set_updated_at();

create trigger patient_conditions_set_updated_at
before update on patient_conditions
for each row execute function set_updated_at();

create trigger patient_allergies_set_updated_at
before update on patient_allergies
for each row execute function set_updated_at();

create trigger patient_medications_set_updated_at
before update on patient_medications
for each row execute function set_updated_at();

create trigger visits_set_updated_at
before update on visits
for each row execute function set_updated_at();

create trigger assessments_set_updated_at
before update on assessments
for each row execute function set_updated_at();

create trigger hospital_submissions_set_updated_at
before update on hospital_submissions
for each row execute function set_updated_at();

create or replace function validate_hospital_submission()
returns trigger
language plpgsql
as $$
declare
    target_capability hospital_capability;
    target_active boolean;
    target_accepting boolean;
    consent_is_valid boolean;
    assessment_is_valid boolean;
    source_visit_status visit_status;
begin
    select capability, is_active, accepting_submissions
    into target_capability, target_active, target_accepting
    from hospitals
    where id = new.hospital_id;

    if target_capability is null or not target_active or not target_accepting then
        raise exception 'Selected hospital is not available for submissions';
    end if;

    if new.severity = 'HIGH' and target_capability <> 'ER_CAPABLE' then
        raise exception 'HIGH severity submissions require an ER_CAPABLE hospital';
    end if;

    select exists (
        select 1
        from consent_events consent
        where consent.id = new.consent_event_id
          and consent.visit_id = new.visit_id
          and consent.hospital_id = new.hospital_id
          and consent.consent_type = 'HOSPITAL_SHARE'
          and consent.granted = true
    ) into consent_is_valid;

    if not consent_is_valid then
        raise exception 'A granted HOSPITAL_SHARE consent for this visit and hospital is required';
    end if;

    select exists (
        select 1
        from assessments assessment
        where assessment.visit_id = new.visit_id
          and assessment.status = 'COMPLETE'
          and assessment.severity = new.severity
          and assessment.recommended_action = new.recommended_action
    ) into assessment_is_valid;

    if not assessment_is_valid then
        raise exception 'Submission severity and action must match a completed assessment';
    end if;

    select status into source_visit_status
    from visits
    where id = new.visit_id;

    if source_visit_status <> 'ASSESSMENT_COMPLETE' then
        raise exception 'Visit must be ASSESSMENT_COMPLETE before submission';
    end if;

    return new;
end;
$$;

create trigger hospital_submissions_validate
before insert on hospital_submissions
for each row execute function validate_hospital_submission();

create or replace function mark_visit_submitted()
returns trigger
language plpgsql
as $$
begin
    update visits
    set status = 'SUBMITTED', completed_at = coalesce(completed_at, now())
    where id = new.visit_id;

    return new;
end;
$$;

create trigger hospital_submissions_mark_visit_submitted
after insert on hospital_submissions
for each row execute function mark_visit_submitted();

create or replace function protect_submission_snapshot()
returns trigger
language plpgsql
as $$
begin
    if new.visit_id is distinct from old.visit_id
        or new.hospital_id is distinct from old.hospital_id
        or new.consent_event_id is distinct from old.consent_event_id
        or new.severity is distinct from old.severity
        or new.recommended_action is distinct from old.recommended_action
        or new.submission_snapshot is distinct from old.submission_snapshot
        or new.arrival_type is distinct from old.arrival_type then
        raise exception 'Submitted clinical snapshot fields are immutable';
    end if;

    return new;
end;
$$;

create trigger hospital_submissions_protect_snapshot
before update on hospital_submissions
for each row execute function protect_submission_snapshot();

insert into schema_migrations (version) values ('001_initial_schema');

commit;
