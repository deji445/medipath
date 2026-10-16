# Emergency escalation scenarios

This document is a prototype safety review checklist for MediPath's deterministic emergency screen. It is not a clinical protocol and must be reviewed by qualified Ghanaian emergency-care clinicians before production use.

The executable phrase corpus is `tests/Fixtures/EmergencyPhraseCorpus.php`. Every phrase in that file must bypass Gemini, create an emergency response, display the Ghana emergency actions, and lock the ordinary AI conversation.

## Worst-case user questions and statements

### Airway and breathing

- “Why can't I breathe?”
- “I have trouble breathing.”
- “She is gasping for air.”
- “He stopped breathing.”
- “My child is turning blue.”
- Variants include missing apostrophes and common spellings such as “cant breath” and “brething”.

### Unconsciousness, seizure, and cardiac arrest

- “Why won't he wake up?”
- “She passed out and is not responding.”
- “The seizure won't stop.”
- “I cannot find a pulse.”
- “His heart has stopped.”

### Severe bleeding

- “Why won't the bleeding stop?”
- “Blood is spurting from the wound.”
- “I'm bleeding from my arm.”
- “I'm vomiting blood.”
- “I'm pregnant and bleeding heavily.”

### Stroke warning signs

- “Why is one side of my face drooping?”
- “My speech is slurred.”
- “Suddenly my arm is weak.”
- “I suddenly cannot see.”

### Chest pain

- “My chest hurts.”
- “I have crushing chest pain.”
- “The pressure in my chest is spreading to my jaw.”

### Severe allergic reaction

- “My throat is closing.”
- “My tongue is swollen.”
- “My lips are swelling.”
- “Is this anaphylaxis?”

### Penetrating injury and major trauma

- “I think iv been shot.”
- “He was shot in the chest.”
- “I was stabbed in my stomach.”
- “There is a hole in my stomach.”
- “The knife is still in my chest.”
- “I was in a serious car crash.”
- “She was thrown from the vehicle.”
- “He fell from the roof.”
- “I broke my leg.”
- “The bone is showing through an open fracture.”
- “My leg was cut off.”

### Burns, electricity, and drowning

- “My child has a severe burn.”
- “He was electrocuted.”
- “I have a chemical burn.”
- “The child is drowning.”
- “We inhaled smoke in the fire.”

### Poisoning and overdose

- “I drank bleach.”
- “My child swallowed pesticide.”
- “I took too many pills.”
- “We inhaled chemical fumes.”
- “She has overdosed.”
- “He was bitten by a snake.”

### Immediate risk of harm

- “I am going to kill myself.”
- “I have a suicide plan.”
- “I want to end my life.”
- “I am about to hurt someone.”

## Required deterministic behaviour

1. Screen every user message before sending anything to Gemini.
2. Screen the complete user-message history so a later short answer cannot erase an earlier red flag.
3. Treat explicit negation such as “I do not have chest pain” as non-current unless another unnegated emergency sign is present.
4. On a match, return no diagnostic content and ask no further question.
5. Display **Call 112 now** and **Find emergency care**.
6. Lock the ordinary chat until the user deliberately starts a new conversation.
7. Never claim that MediPath has dispatched an ambulance.

## Reference basis

- [Ghana National Ambulance Service](https://www.nas.gov.gh/) identifies 112 as Ghana's national emergency number.
- [WHO Community First Aid Response](https://cdn.who.int/media/docs/default-source/integrated-health-services-%28ihs%29/csy/cfar-pocketguide.pdf) prioritizes severe bleeding, airway obstruction, breathing difficulty, shock, altered mental status, disability, wounds, burns, and bites.
- [CDC stroke guidance](https://www.cdc.gov/stroke/signs-symptoms/index.html) identifies sudden balance, vision, facial, limb, speech, and severe-headache changes as warning signs requiring immediate action.
- [North West Ambulance Service emergency guidance](https://www.nwas.nhs.uk/services/urgent-and-emergency-care-999/when-to-call-999/) includes breathing difficulty, severe bleeding, loss of consciousness, seizures, chest pain, severe allergy, burns, stroke, head injury, stabbing, shooting, and serious road crashes.
