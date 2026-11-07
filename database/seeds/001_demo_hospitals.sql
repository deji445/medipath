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
    longitude,
    is_live_partner,
    directory_source_url,
    directory_verified_at
)
values
    (
        '00000000-0000-4000-8000-000000000001',
        'korle-bu-teaching-hospital',
        'Korle Bu Teaching Hospital',
        'ER_CAPABLE',
        'MANUAL',
        true,
        true,
        'Korle Bu Teaching Hospital',
        '#197F6D',
        'Guggisberg Avenue, Korle Bu, Accra · GA-221-1570',
        5.537430,
        -0.227400,
        false,
        'https://kbth.gov.gh/',
        '2026-11-07 00:00:00+00'
    ),
    (
        '00000000-0000-4000-8000-000000000002',
        'adabraka-polyclinic',
        'Adabraka Polyclinic',
        'CLINIC_ONLY',
        'MANUAL',
        true,
        true,
        'Adabraka Polyclinic',
        '#197F6D',
        'Castle Road, Adabraka, Accra · GA-050-3303',
        5.561884,
        -0.205108,
        false,
        'https://www.ghaneps.gov.gh/epps/prepareViewCAOrganisation.do?id=20629',
        '2026-11-07 00:00:00+00'
    ),
    (
        '00000000-0000-4000-8000-000000000003',
        'greater-accra-regional-hospital',
        'Greater Accra Regional Hospital',
        'ER_CAPABLE',
        'MANUAL',
        true,
        true,
        'Greater Accra Regional Hospital',
        '#197F6D',
        'Castle Road, Ridge, Accra · GA-052-7021',
        5.562110,
        -0.198930,
        false,
        'https://garh.gov.gh/services/accidents-emergency-service/',
        '2026-11-07 00:00:00+00'
    )
on conflict (id) do update set
    slug = excluded.slug,
    name = excluded.name,
    capability = excluded.capability,
    routing_mode = excluded.routing_mode,
    accepting_submissions = excluded.accepting_submissions,
    is_active = excluded.is_active,
    brand_name = excluded.brand_name,
    brand_color = excluded.brand_color,
    address = excluded.address,
    latitude = excluded.latitude,
    longitude = excluded.longitude,
    is_live_partner = excluded.is_live_partner,
    directory_source_url = excluded.directory_source_url,
    directory_verified_at = excluded.directory_verified_at;

commit;

