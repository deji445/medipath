import fs from 'node:fs/promises';
import path from 'node:path';
import { FileBlob, SpreadsheetFile, Workbook } from '@oai/artifact-tool';

const projectRoot = process.cwd();
const outputPath = path.join(projectRoot, 'docs', 'twi-clinical-review.xlsx');
const previewDir = path.join(projectRoot, 'storage', 'app', 'review-previews');

function decode(value) {
    return value.replaceAll("\\'", "'").replaceAll('\\n', '\n');
}

async function uiTranslations() {
    const files = [
        path.join(projectRoot, 'resources', 'js', 'i18n', 'twi.ts'),
        path.join(projectRoot, 'resources', 'js', 'i18n', 'twiPages.ts'),
    ];
    const entries = new Map();
    const pattern =
        /(?:^|\n)\s*(?:'((?:\\'|[^'])*)'|([A-Za-z][A-Za-z0-9_ ]*)):\s*(?:\r?\n\s*)?'((?:\\'|[^'])*)',/g;

    for (const file of files) {
        const source = await fs.readFile(file, 'utf8');
        for (const match of source.matchAll(pattern)) {
            const english = decode(match[1] ?? match[2]);
            entries.set(english, decode(match[3]));
        }
    }

    return [...entries.entries()].map(([english, twi], index) => {
        const clinical =
            /emergency|diagnos|patient|hospital|symptom|bleed|breath|injur|poison|pregnan|medicine|allerg|pain|stroke|seizure|vital|care|health/i.test(
                english,
            );
        const area = /hospital|admission|staff|tenant|platform/i.test(english)
            ? 'Hospital operations'
            : /emergency|warning|112|urgent/i.test(english)
              ? 'Emergency'
              : /assessment|symptom|diagnos|health|medicine|allerg|vital/i.test(
                      english,
                  )
                ? 'Clinical intake'
                : /login|password|account|profile|settings/i.test(english)
                  ? 'Account'
                  : 'General interface';

        return [
            `UI-${String(index + 1).padStart(3, '0')}`,
            area,
            clinical ? 'Clinical review' : 'Language review',
            english,
            twi,
            'Pending',
            '',
            null,
            '',
        ];
    });
}

const emergencyCandidates = [
    [
        'TW-ER-001',
        'Severe breathing difficulty',
        'I cannot breathe',
        'Mentumi nhome.',
        'Immediate escalation',
    ],
    [
        'TW-ER-002',
        'Severe breathing difficulty',
        'Breathing is difficult',
        'Me home yɛ den.',
        'Immediate escalation',
    ],
    [
        'TW-ER-003',
        'Severe breathing difficulty',
        'The person has stopped breathing',
        'Ɔrenhome bio.',
        'Immediate escalation',
    ],
    [
        'TW-ER-004',
        'Severe breathing difficulty',
        'The child is gasping for air',
        'Abofra no repere sɛ ɔbɛhome.',
        'Immediate escalation',
    ],
    [
        'TW-ER-005',
        'Unresponsive or neurological change',
        'The person will not wake up',
        'Ɔnyane koraa.',
        'Immediate escalation',
    ],
    [
        'TW-ER-006',
        'Unresponsive or neurological change',
        'The person is unconscious',
        'Ɔnte ne ho.',
        'Immediate escalation',
    ],
    [
        'TW-ER-007',
        'Unresponsive or neurological change',
        'The person is having a seizure',
        'Ɔreyɛ fit.',
        'Immediate escalation',
    ],
    [
        'TW-ER-008',
        'Unresponsive or neurological change',
        'The seizure will not stop',
        'Fit no nnyae.',
        'Immediate escalation',
    ],
    [
        'TW-ER-009',
        'Severe uncontrolled bleeding',
        'I am bleeding heavily',
        'Mogya pii refi me.',
        'Immediate escalation',
    ],
    [
        'TW-ER-010',
        'Severe uncontrolled bleeding',
        'The bleeding will not stop',
        'Mogya no rentwa.',
        'Immediate escalation',
    ],
    [
        'TW-ER-011',
        'Severe uncontrolled bleeding',
        'Blood is spurting from the wound',
        'Mogya rebɔ fi opira no mu.',
        'Immediate escalation',
    ],
    [
        'TW-ER-012',
        'Severe uncontrolled bleeding',
        'I am vomiting blood',
        'Merefe mogya.',
        'Immediate escalation',
    ],
    [
        'TW-ER-013',
        'Severe uncontrolled bleeding',
        'I am coughing blood',
        'Merekɔ mogya.',
        'Immediate escalation',
    ],
    [
        'TW-ER-014',
        'Possible stroke',
        'One side of the face is drooping',
        'N’anim fã baako abɔ fam.',
        'Immediate escalation',
    ],
    [
        'TW-ER-015',
        'Possible stroke',
        'Speech suddenly became unclear',
        'N’ano asɛe prɛko.',
        'Immediate escalation',
    ],
    [
        'TW-ER-016',
        'Possible stroke',
        'One arm suddenly became weak',
        'Ne nsa baako ayɛ mmerɛw prɛko.',
        'Immediate escalation',
    ],
    [
        'TW-ER-017',
        'Possible stroke',
        'Sudden loss of vision',
        'Ɔnhu ade prɛko.',
        'Immediate escalation',
    ],
    [
        'TW-ER-018',
        'Concerning chest pain',
        'My chest hurts',
        'Me koko yɛ me yaw.',
        'Immediate escalation',
    ],
    [
        'TW-ER-019',
        'Concerning chest pain',
        'There is heavy pressure on my chest',
        'Me koko so yɛ me duru.',
        'Immediate escalation',
    ],
    [
        'TW-ER-020',
        'Severe allergic reaction',
        'My throat is closing',
        'Me menewa retum.',
        'Immediate escalation',
    ],
    [
        'TW-ER-021',
        'Severe allergic reaction',
        'My tongue is swollen',
        'Me tɛkrɛma ahono.',
        'Immediate escalation',
    ],
    [
        'TW-ER-022',
        'Severe allergic reaction',
        'My lips are swelling',
        'M’anofafa rehono.',
        'Immediate escalation',
    ],
    [
        'TW-ER-023',
        'Major trauma',
        'I have been shot',
        'Wɔato me tuo.',
        'Immediate escalation',
    ],
    [
        'TW-ER-024',
        'Major trauma',
        'I have been stabbed',
        'Wɔde sekan awɔ me.',
        'Immediate escalation',
    ],
    [
        'TW-ER-025',
        'Major trauma',
        'My leg is broken',
        'Me nan abu.',
        'Immediate escalation',
    ],
    [
        'TW-ER-026',
        'Major trauma',
        'My arm is broken',
        'Me nsa abu.',
        'Immediate escalation',
    ],
    [
        'TW-ER-027',
        'Major trauma',
        'My leg has been cut off',
        'Wɔatwa me nan.',
        'Immediate escalation',
    ],
    [
        'TW-ER-028',
        'Major burn',
        'I have a severe burn',
        'Ogya ahye me kɛse.',
        'Immediate escalation',
    ],
    [
        'TW-ER-029',
        'Poisoning',
        'I drank bleach',
        'Manom bleach.',
        'Immediate escalation',
    ],
    [
        'TW-ER-030',
        'Poisoning',
        'The child swallowed pesticide',
        'Abofra no amene nnuru a wɔde kum mmoawa.',
        'Immediate escalation',
    ],
    [
        'TW-ER-031',
        'Poisoning',
        'I took too many tablets',
        'Manom aduru a ɛboro so.',
        'Immediate escalation',
    ],
    [
        'TW-ER-032',
        'Snake bite',
        'A snake bit me',
        'Ɔwɔ aka me.',
        'Immediate escalation',
    ],
    [
        'TW-ER-033',
        'Immediate harm intent',
        'I want to kill myself',
        'Mepɛ sɛ mikum me ho.',
        'Immediate escalation',
    ],
    [
        'TW-ER-034',
        'Immediate harm intent',
        'I want to end my life',
        'Mepɛ sɛ mewie me nkwa.',
        'Immediate escalation',
    ],
    [
        'TW-ER-035',
        'Immediate harm intent',
        'I am about to hurt someone',
        'Merekɔpira obi.',
        'Immediate escalation',
    ],
].map((row) => [
    ...row,
    'Critical',
    'Draft; requires fluent Twi clinician review and spoken-variant testing',
    'Pending',
    '',
    null,
    '',
]);

const clinicalResponses = [
    [
        'CL-001',
        'Emergency response',
        'This may be an emergency. Call 112 now. Do not wait for another chat reply.',
        'Ebia eyi yɛ tebea a ɛyɛ ntɛm. Frɛ 112 seesei. Ntwɛn nkɔmmɔbɔ mmuae foforo.',
    ],
    [
        'CL-002',
        'Bleeding first aid',
        'Use a clean cloth or dressing to apply firm, continuous pressure to the wound.',
        'Fa ntama anaa bandage a ɛho tew mia opira no so denneennen na nkɔ so mia so.',
    ],
    [
        'CL-003',
        'Bleeding first aid',
        'If blood soaks through, add another cloth or dressing on top. Keep the first one in place.',
        'Sɛ mogya fa ntama no mu a, fa foforo gu so. Nyiyi nea edi kan no.',
    ],
    [
        'CL-004',
        'Embedded object first aid',
        'If something is stuck in the wound, leave it in place and press around it, not on it.',
        'Sɛ biribi aka opira no mu a, nyiyi. Mia fa ho hyia, na ɛnyɛ ade no so.',
    ],
    [
        'CL-005',
        'Fracture first aid',
        'Keep the injured limb still. Do not straighten it or put weight on it.',
        'Ma honam akwaa a apira no nna hɔ komm. Ntene na mfa wo mu duru nhyɛ so.',
    ],
    [
        'CL-006',
        'Movement warning',
        'Do not move the person unless the area is unsafe.',
        'Mma onipa no mpɔnkɔ gye sɛ beae no yɛ hu.',
    ],
    [
        'CL-007',
        'Dispatcher instruction',
        "Keep the person comfortable and follow the 112 dispatcher's instructions.",
        'Ma onipa no ho nto no na di 112 adwumayɛni akwankyerɛ akyi.',
    ],
].map((row) => [...row, 'Critical', 'Pending', '', null, '']);

function styleTitle(sheet, range) {
    range.format.font = {
        name: 'Arial',
        size: 15,
        bold: true,
        color: '#173A33',
    };
    range.format.rowHeight = 25;
    sheet.showGridLines = false;
}

function styleTable(sheet, rangeAddress, headerAddress, widths) {
    const range = sheet.getRange(rangeAddress);
    range.format.font = { name: 'Arial', size: 10, color: '#173A33' };
    range.format.verticalAlignment = 'top';
    const header = sheet.getRange(headerAddress);
    header.format = {
        fill: '#197F6D',
        font: { name: 'Arial', size: 10, bold: true, color: '#FFFFFF' },
        verticalAlignment: 'center',
        horizontalAlignment: 'center',
        wrapText: true,
        borders: { preset: 'inside', style: 'thin', color: '#DFF2EC' },
    };
    for (const [column, width] of Object.entries(widths)) {
        sheet.getRange(`${column}:${column}`).format.columnWidth = width;
    }
}

function addStatusRules(sheet, rangeAddress) {
    const range = sheet.getRange(rangeAddress);
    range.dataValidation = {
        rule: {
            type: 'list',
            values: ['Pending', 'Approved', 'Needs changes'],
        },
    };
    range.conditionalFormats.add('containsText', {
        text: 'Approved',
        format: { fill: '#DFF2EC', font: { bold: true, color: '#116655' } },
    });
    range.conditionalFormats.add('containsText', {
        text: 'Pending',
        format: { fill: '#FFF4D6', font: { color: '#7A4D00' } },
    });
    range.conditionalFormats.add('containsText', {
        text: 'Needs changes',
        format: { fill: '#FFF0F2', font: { bold: true, color: '#8A2637' } },
    });
}

const uiRows = await uiTranslations();
const workbook = Workbook.create();
const guide = workbook.worksheets.add('Review guide');
const uiSheet = workbook.worksheets.add('UI translations');
const emergencySheet = workbook.worksheets.add('Emergency phrases');
const clinicalSheet = workbook.worksheets.add('Clinical responses');

guide.getRange('A2:F2').merge();
guide.getRange('A2').values = [['MediPath Twi clinical language review']];
styleTitle(guide, guide.getRange('A2:F2'));
guide.getRange('A4:B11').values = [
    [
        'Purpose',
        'A controlled sign-off workflow for Twi interface, emergency-detection, and clinical-response wording.',
    ],
    [
        'Required reviewers',
        'A fluent Ghanaian Twi speaker and a Ghana-licensed clinician or emergency-care professional.',
    ],
    [
        'Approval rule',
        'Set Status to Approved only after meaning, urgency, clarity, pronunciation, and likely spoken variants have been checked.',
    ],
    [
        'Important',
        'Every current row is a draft. This workbook does not claim that any phrase has already received human review.',
    ],
    [
        'Runtime rule',
        'Do not add a Twi emergency phrase to deterministic escalation until its row is Approved with reviewer and date completed.',
    ],
    [
        'Primary source',
        'Ghana National Ambulance Service confirms 112 as the nationwide emergency hotline.',
    ],
    ['Source URL', 'https://nas.gov.gh/services.php'],
    [
        'Source limitation',
        'No authoritative public Twi emergency-phrase lexicon was found. Wording therefore requires direct human validation.',
    ],
];
guide.getRange('A13:B19').values = [
    ['Review measure', 'Open items'],
    ['UI translations', null],
    ['Emergency phrases', null],
    ['Clinical responses', null],
    ['Total approved', null],
    ['Needs changes', null],
    ['Reviewer sign-off complete', null],
];
guide.getRange('B14').formulas = [
    [`=COUNTIF('UI translations'!F2:F${uiRows.length + 1},"Pending")`],
];
guide.getRange('B15').formulas = [
    [
        `=COUNTIF('Emergency phrases'!H2:H${emergencyCandidates.length + 1},"Pending")`,
    ],
];
guide.getRange('B16').formulas = [
    [
        `=COUNTIF('Clinical responses'!F2:F${clinicalResponses.length + 1},"Pending")`,
    ],
];
guide.getRange('B17').formulas = [
    [
        `=COUNTIF('UI translations'!F2:F${uiRows.length + 1},"Approved")+COUNTIF('Emergency phrases'!H2:H${emergencyCandidates.length + 1},"Approved")+COUNTIF('Clinical responses'!F2:F${clinicalResponses.length + 1},"Approved")`,
    ],
];
guide.getRange('B18').formulas = [
    [
        `=COUNTIF('UI translations'!F2:F${uiRows.length + 1},"Needs changes")+COUNTIF('Emergency phrases'!H2:H${emergencyCandidates.length + 1},"Needs changes")+COUNTIF('Clinical responses'!F2:F${clinicalResponses.length + 1},"Needs changes")`,
    ],
];
guide.getRange('B19').formulas = [['=IF(SUM(B14:B16)=0,"Yes","No")']];
guide.getRange('A4:A11').format.font = {
    name: 'Arial',
    size: 10,
    bold: true,
    color: '#173A33',
};
guide.getRange('B4:B11').format = {
    font: { name: 'Arial', size: 10, color: '#45635B' },
    wrapText: true,
};
guide.getRange('A13:B13').format = {
    fill: '#197F6D',
    font: { name: 'Arial', size: 10, bold: true, color: '#FFFFFF' },
};
guide.getRange('A13:B19').format.borders = {
    preset: 'inside',
    style: 'thin',
    color: '#D2E2DC',
};
guide.getRange('A1:A20').format.columnWidth = 29;
guide.getRange('B1:B20').format.columnWidth = 86;
guide.freezePanes.freezeRows(2);

uiSheet.getRange('A1:I1').values = [
    [
        'ID',
        'Area',
        'Review level',
        'English source',
        'Current Twi draft',
        'Status',
        'Reviewer',
        'Review date',
        'Notes or corrected Twi',
    ],
];
uiSheet.getRange(`A2:I${uiRows.length + 1}`).values = uiRows;
styleTable(uiSheet, `A1:I${uiRows.length + 1}`, 'A1:I1', {
    A: 12,
    B: 22,
    C: 18,
    D: 58,
    E: 58,
    F: 17,
    G: 22,
    H: 15,
    I: 42,
});
uiSheet.getRange(`D2:E${uiRows.length + 1}`).format.wrapText = true;
uiSheet.getRange(`I2:I${uiRows.length + 1}`).format.wrapText = true;
uiSheet.getRange(`H2:H${uiRows.length + 1}`).setNumberFormat('yyyy-mm-dd');
addStatusRules(uiSheet, `F2:F${uiRows.length + 1}`);
uiSheet.freezePanes.freezeRows(1);
uiSheet.freezePanes.freezeColumns(3);
uiSheet.tables.add(`A1:I${uiRows.length + 1}`, true, 'UiTranslationReview');

emergencySheet.getRange('A1:K1').values = [
    [
        'ID',
        'Red-flag category',
        'English meaning',
        'Proposed Twi phrase',
        'Expected action',
        'Risk',
        'Source or limitation',
        'Status',
        'Reviewer',
        'Review date',
        'Notes or corrected Twi',
    ],
];
emergencySheet.getRange(`A2:K${emergencyCandidates.length + 1}`).values =
    emergencyCandidates;
styleTable(emergencySheet, `A1:K${emergencyCandidates.length + 1}`, 'A1:K1', {
    A: 13,
    B: 30,
    C: 38,
    D: 38,
    E: 22,
    F: 12,
    G: 48,
    H: 17,
    I: 22,
    J: 15,
    K: 42,
});
emergencySheet.getRange(
    `B2:K${emergencyCandidates.length + 1}`,
).format.wrapText = true;
emergencySheet
    .getRange(`J2:J${emergencyCandidates.length + 1}`)
    .setNumberFormat('yyyy-mm-dd');
addStatusRules(emergencySheet, `H2:H${emergencyCandidates.length + 1}`);
emergencySheet.freezePanes.freezeRows(1);
emergencySheet.freezePanes.freezeColumns(2);
emergencySheet.tables.add(
    `A1:K${emergencyCandidates.length + 1}`,
    true,
    'EmergencyPhraseReview',
);

clinicalSheet.getRange('A1:I1').values = [
    [
        'ID',
        'Clinical use',
        'English source',
        'Current Twi draft',
        'Risk',
        'Status',
        'Reviewer',
        'Review date',
        'Notes or corrected Twi',
    ],
];
clinicalSheet.getRange(`A2:I${clinicalResponses.length + 1}`).values =
    clinicalResponses;
styleTable(clinicalSheet, `A1:I${clinicalResponses.length + 1}`, 'A1:I1', {
    A: 12,
    B: 24,
    C: 60,
    D: 60,
    E: 12,
    F: 17,
    G: 22,
    H: 15,
    I: 42,
});
clinicalSheet.getRange(`B2:I${clinicalResponses.length + 1}`).format.wrapText =
    true;
clinicalSheet
    .getRange(`H2:H${clinicalResponses.length + 1}`)
    .setNumberFormat('yyyy-mm-dd');
addStatusRules(clinicalSheet, `F2:F${clinicalResponses.length + 1}`);
clinicalSheet.freezePanes.freezeRows(1);
clinicalSheet.freezePanes.freezeColumns(2);
clinicalSheet.tables.add(
    `A1:I${clinicalResponses.length + 1}`,
    true,
    'ClinicalResponseReview',
);

workbook.recalculate();
await fs.mkdir(path.dirname(outputPath), { recursive: true });
await fs.mkdir(previewDir, { recursive: true });

for (const [sheetName, range] of [
    ['Review guide', 'A1:F20'],
    ['UI translations', 'A1:I22'],
    ['Emergency phrases', 'A1:K18'],
    ['Clinical responses', 'A1:I9'],
]) {
    const preview = await workbook.render({ sheetName, range, scale: 1 });
    await fs.writeFile(
        path.join(
            previewDir,
            `${sheetName.replaceAll(' ', '-').toLowerCase()}.png`,
        ),
        new Uint8Array(await preview.arrayBuffer()),
    );
}

const errors = await workbook.inspect({
    kind: 'match',
    searchTerm:
        '#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A|#NUM!|#NULL!|#SPILL!|#CALC!',
    options: { useRegex: true, maxResults: 100 },
    summary: 'final formula error scan',
});
console.log(errors.ndjson);

const output = await SpreadsheetFile.exportXlsx(workbook);
await output.save(outputPath);
const saved = await FileBlob.load(outputPath);
const verifiedWorkbook = await SpreadsheetFile.importXlsx(saved);
const verification = await verifiedWorkbook.inspect({
    kind: 'sheet',
    include: 'id,name',
    summary: 'saved workbook sheet verification',
});
console.log(verification.ndjson);
console.log(
    JSON.stringify({
        outputPath,
        uiRows: uiRows.length,
        emergencyRows: emergencyCandidates.length,
    }),
);
