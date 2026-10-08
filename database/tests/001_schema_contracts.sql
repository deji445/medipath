begin;

insert into patients (
    id,
    normalized_phone,
    first_name,
    last_name
)
values (
    '10000000-0000-4000-8000-000000000001',
    '+233201234567',
    'Schema',
    'Test'
);

insert into visits (
    id,
    patient_id,
    status,
    filling_as,
    age_years
)
values (
    '20000000-0000-4000-8000-000000000001',
    '10000000-0000-4000-8000-000000000001',
    'ASSESSMENT_COMPLETE',
    'SELF',
    35
);

insert into assessments (
    id,
    visit_id,
    status,
    severity,
    recommended_action,
    symptom_summary,
    red_flags,
    reason_codes,
    deterministic_red_flag_match,
    question_count
)
values (
    '30000000-0000-4000-8000-000000000001',
    '20000000-0000-4000-8000-000000000001',
    'COMPLETE',
    'HIGH',
    'SEEK_EMERGENCY_CARE_NOW',
    'Test summary for a high-acuity schema contract.',
    '["SEVERE_BREATHING_DIFFICULTY"]'::jsonb,
    '["DETERMINISTIC_RED_FLAG"]'::jsonb,
    true,
    2
);

insert into consent_events (
    id,
    patient_id,
    visit_id,
    hospital_id,
    consent_type,
    consent_version,
    granted
)
values
    (
        '40000000-0000-4000-8000-000000000001',
        '10000000-0000-4000-8000-000000000001',
        '20000000-0000-4000-8000-000000000001',
        '00000000-0000-4000-8000-000000000002',
        'HOSPITAL_SHARE',
        '1.0',
        true
    ),
    (
        '40000000-0000-4000-8000-000000000002',
        '10000000-0000-4000-8000-000000000001',
        '20000000-0000-4000-8000-000000000001',
        '00000000-0000-4000-8000-000000000001',
        'HOSPITAL_SHARE',
        '1.0',
        true
    );

do $$
begin
    begin
        insert into hospital_submissions (
            visit_id,
            hospital_id,
            consent_event_id,
            arrival_type,
            severity,
            recommended_action,
            submission_snapshot
        )
        values (
            '20000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            '40000000-0000-4000-8000-000000000001',
            'PRE_ARRIVAL',
            'HIGH',
            'SEEK_EMERGENCY_CARE_NOW',
            '{"test": true}'::jsonb
        );

        raise exception 'TEST_FAILED_HIGH_TO_CLINIC_WAS_ACCEPTED';
    exception
        when raise_exception then
            if sqlerrm = 'TEST_FAILED_HIGH_TO_CLINIC_WAS_ACCEPTED' then
                raise;
            end if;
            if sqlerrm <> 'HIGH severity submissions require an ER_CAPABLE hospital' then
                raise;
            end if;
    end;
end;
$$;

insert into hospital_submissions (
    id,
    visit_id,
    hospital_id,
    consent_event_id,
    arrival_type,
    severity,
    recommended_action,
    submission_snapshot
)
values (
    '50000000-0000-4000-8000-000000000001',
    '20000000-0000-4000-8000-000000000001',
    '00000000-0000-4000-8000-000000000001',
    '40000000-0000-4000-8000-000000000002',
    'PRE_ARRIVAL',
    'HIGH',
    'SEEK_EMERGENCY_CARE_NOW',
    '{"test": true}'::jsonb
);

do $$
declare
    current_visit_status visit_status;
begin
    select status into current_visit_status
    from visits
    where id = '20000000-0000-4000-8000-000000000001';

    if current_visit_status <> 'SUBMITTED' then
        raise exception 'TEST_FAILED_VISIT_NOT_MARKED_SUBMITTED';
    end if;
end;
$$;

rollback;

