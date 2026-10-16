<?php

namespace Tests\Fixtures;

use App\Enums\RedFlagCode;

final class EmergencyPhraseCorpus
{
    /** @return iterable<string, array{string, RedFlagCode}> */
    public static function cases(): iterable
    {
        yield 'breathing: cannot breathe' => ['I cannot breathe', RedFlagCode::SevereBreathingDifficulty];
        yield 'breathing: common typo' => ['I cant breath', RedFlagCode::SevereBreathingDifficulty];
        yield 'breathing: trouble breathing' => ['I have trouble breathing', RedFlagCode::SevereBreathingDifficulty];
        yield 'breathing: gasping' => ['She is gasping for air', RedFlagCode::SevereBreathingDifficulty];
        yield 'breathing: stopped' => ['He has stopped breathing', RedFlagCode::SevereBreathingDifficulty];
        yield 'breathing: child turning blue' => ['My child is turning blue', RedFlagCode::SevereBreathingDifficulty];

        yield 'consciousness: will not wake' => ['He won\'t wake up', RedFlagCode::UnresponsiveOrSevereNeurologicalChange];
        yield 'consciousness: passed out' => ['She passed out and is not responding', RedFlagCode::UnresponsiveOrSevereNeurologicalChange];
        yield 'consciousness: seizure' => ['He is having a seizure', RedFlagCode::UnresponsiveOrSevereNeurologicalChange];
        yield 'consciousness: seizure will not stop' => ['The fit won\'t stop', RedFlagCode::UnresponsiveOrSevereNeurologicalChange];
        yield 'consciousness: no pulse' => ['I cannot find a pulse', RedFlagCode::UnresponsiveOrSevereNeurologicalChange];
        yield 'consciousness: sudden confusion' => ['She has sudden severe confusion', RedFlagCode::UnresponsiveOrSevereNeurologicalChange];

        yield 'bleeding: heavy' => ['I am bleeding heavily', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: will not stop' => ['The bleeding will not stop', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: spurting' => ['Blood is spurting from the wound', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: arm' => ['I am bleeding from my arm', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: eyes typo' => ['My eyes are bleading', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: vomiting blood typo' => ['I am vomitting blood', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: coughing blood' => ['He is coughing blood', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: pooping blood' => ['I am pooping blood', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: blood in stool' => ['There is blood in my stool', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: rectal bleeding' => ['I have rectal bleeding', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: black tarry stool' => ['My stool is black and tarry', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: peeing blood' => ['I am peeing blood', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: blood in urine' => ['I can see blood in my urine', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: blood when urinating' => ['There is blood when I urinate', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: bloody urine' => ['My urine looks bloody', RedFlagCode::SevereUncontrolledBleeding];
        yield 'bleeding: pregnancy' => ['I am pregnant and bleeding heavily', RedFlagCode::SevereUncontrolledBleeding];

        yield 'stroke: face droop' => ['One side of my face is drooping', RedFlagCode::PossibleStroke];
        yield 'stroke: slurred speech' => ['My speech is slurred', RedFlagCode::PossibleStroke];
        yield 'stroke: sudden arm weakness' => ['Suddenly my arm is weak', RedFlagCode::PossibleStroke];
        yield 'stroke: sudden vision loss' => ['I suddenly cannot see', RedFlagCode::PossibleStroke];

        yield 'chest: plain pain' => ['My chest hurts', RedFlagCode::ConcerningChestPain];
        yield 'chest: crushing pain' => ['I have crushing chest pain', RedFlagCode::ConcerningChestPain];
        yield 'chest: pressure spreading' => ['Chest pressure is spreading to my jaw', RedFlagCode::ConcerningChestPain];

        yield 'allergy: throat closing' => ['My throat is closing', RedFlagCode::SevereAllergicReaction];
        yield 'allergy: tongue swollen' => ['My tongue is swollen', RedFlagCode::SevereAllergicReaction];
        yield 'allergy: lips swelling' => ['My lips are swelling', RedFlagCode::SevereAllergicReaction];
        yield 'allergy: anaphylaxis' => ['I think this is anaphylaxis', RedFlagCode::SevereAllergicReaction];

        yield 'trauma: exact reported shot variant' => ['I think iv been shot', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: was shot' => ['He was shot in the chest', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: bullet wound' => ['There is a bullet wound', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: stabbed typo' => ['I was stabed in my stomach', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: abdominal hole' => ['There is a hole in my stomach', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: retained knife' => ['The knife is still in my chest', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: serious road crash' => ['I was in a serious car crash', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: ejected from vehicle' => ['She was thrown from the vehicle', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: fall from height' => ['He fell from the roof', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: head injury' => ['This is a serious head injury', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: explosion injury' => ['I have a blast injury', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: broken leg' => ['I broke my leg', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: suspected broken arm' => ['My arm seems broken', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: open fracture' => ['The bone is showing through an open fracture', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: severed limb' => ['My leg was cut off', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: severe burn' => ['My child has a severe burn', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: chemical burn' => ['I have a chemical burn', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: electrocution' => ['He was electrocuted', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: drowning' => ['The child is drowning', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'trauma: smoke inhalation' => ['We inhaled smoke in the fire', RedFlagCode::MajorTraumaBurnOrPoisoning];

        yield 'poisoning: bleach' => ['I drank bleach', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'poisoning: pesticide' => ['My child swallowed pesticide', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'poisoning: pills' => ['I took too many pills', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'poisoning: fumes' => ['We inhaled chemical fumes', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'poisoning: overdose' => ['She has overdosed', RedFlagCode::MajorTraumaBurnOrPoisoning];
        yield 'poisoning: snake bite' => ['He was bitten by a snake', RedFlagCode::MajorTraumaBurnOrPoisoning];

        yield 'harm: suicide intent' => ['I am going to kill myself', RedFlagCode::ImmediateHarmIntent];
        yield 'harm: suicide plan' => ['I have a suicide plan', RedFlagCode::ImmediateHarmIntent];
        yield 'harm: end life' => ['I want to end my life', RedFlagCode::ImmediateHarmIntent];
        yield 'harm: another person' => ['I am about to hurt someone', RedFlagCode::ImmediateHarmIntent];
    }
}
