begin;

insert into hospitals (
    id,
    slug,
    name,
    capability,
    routing_mode,
    accepting_submissions,
    is_active,
    brand_name,
    brand_color,
    address,
    latitude,
    longitude
)
values
    (
        '00000000-0000-4000-8000-000000000001',
        'hospital-a',
        'MediPath Demo General Hospital',
        'ER_CAPABLE',
        'AUTO',
        true,
        true,
        'MediPath General',
        '#0F5C5E',
        'Demo location A, Accra',
        5.603717,
        -0.186964
    ),
    (
        '00000000-0000-4000-8000-000000000002',
        'hospital-b',
        'MediPath Demo Community Clinic',
        'CLINIC_ONLY',
        'MANUAL',
        true,
        true,
        'Community Clinic',
        '#315A8C',
        'Demo location B, Accra',
        5.613717,
        -0.176964
    ),
    (
        '00000000-0000-4000-8000-000000000003',
        'hospital-c',
        'MediPath Demo Emergency Centre',
        'ER_CAPABLE',
        'MANUAL',
        true,
        true,
        'MediPath Emergency Centre',
        '#374151',
        'Demo location C, Accra',
        5.593717,
        -0.196964
    )
on conflict (slug) do update set
    name = excluded.name,
    capability = excluded.capability,
    routing_mode = excluded.routing_mode,
    accepting_submissions = excluded.accepting_submissions,
    is_active = excluded.is_active,
    brand_name = excluded.brand_name,
    brand_color = excluded.brand_color,
    address = excluded.address,
    latitude = excluded.latitude,
    longitude = excluded.longitude;

commit;

