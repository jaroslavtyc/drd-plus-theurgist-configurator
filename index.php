<?php
namespace DrdPlus\Theurgist\Configurator;

use DrdPlus\Tables\Tables;
use DrdPlus\Theurgist\Codes\FormulaCode;
use DrdPlus\Theurgist\Codes\ModifierCode;
use DrdPlus\Theurgist\Formulas\CastingParameters\Affection;
use DrdPlus\Theurgist\Formulas\CastingParameters\SpellTraitsTable;
use DrdPlus\Theurgist\Formulas\FormulasTable;
use DrdPlus\Theurgist\Formulas\ModifiersTable;

require_once __DIR__ . '/vendor/autoload.php';

error_reporting(-1);
ini_set('display_errors', '1');

$modifiersTable = new ModifiersTable(Tables::getIt());
$spellTraitsTable = new SpellTraitsTable();
$formulasTable = new FormulasTable(Tables::getIt(), $modifiersTable, $spellTraitsTable);
$selectedFormula = FormulaCode::getIt($_GET['formula'] ?? current(FormulaCode::getPossibleValues()));
$previouslySelectedFormula = $_GET['previousFormula'] ?? false;
$buildModifiers = function (array $modifierValues) use (&$buildModifiers) {
    $modifiers = [];
    foreach ($modifierValues as $modifierValue => $linkedModifiers) {
        if (is_array($linkedModifiers)) {
            $modifiers[$modifierValue] = $buildModifiers($linkedModifiers); // tree structure
        } else {
            $modifiers[$modifierValue] = []; // dead end
        }
    }

    return $modifiers;
};
$selectedModifierIndexes = [];
if (!empty($_GET['modifiers']) && $selectedFormula->getValue() === $previouslySelectedFormula) {
    $selectedModifierIndexes = $buildModifiers((array)$_GET['modifiers']);
}
$modifierCombinations = [];
if (count($selectedModifierIndexes) > 0) {
    $buildPossibleModifiers = function (array $modifierValues) use (&$buildPossibleModifiers, $modifiersTable) {
        $modifiers = [];
        foreach ($modifierValues as $modifierValue => $relatedModifierValues) {
            if (!array_key_exists($modifierValue, $modifiers)) { // otherwise skip already processed relating modifiers
                $modifierCode = ModifierCode::getIt($modifierValue);
                foreach ($modifiersTable->getChildModifiers($modifierCode) as $relatedModifierCode) {
                    // by-related-modifier-indexed flat array
                    $modifiers[$modifierValue][$relatedModifierCode->getValue()] = $relatedModifierCode;
                }
            }
            // tree structure
            foreach ($buildPossibleModifiers($relatedModifierValues) as $relatedModifierValue => $relatedModifiers) {
                // into flat array
                $modifiers[$relatedModifierValue] = $relatedModifiers; // can overrides previously set (would be the very same so no harm)
            }
        }

        return $modifiers;
    };
    $modifierCombinations = $buildPossibleModifiers($selectedModifierIndexes);
}
?>
<!DOCTYPE html>
<html lang="cs" xmlns="http://www.w3.org/1999/html">
<head>
    <title>Formule pro DrD+ theurga</title>
    <meta http-equiv="Content-type" content="text/html;charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <link rel="stylesheet" type="text/css" href="css/socials.css">
    <noscript>
        <link rel="stylesheet" type="text/css" href="css/no_script.css">
    </noscript>
    <script src="js/main.js"></script>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/github-fork-ribbon-css/0.2.0/gh-fork-ribbon.min.css"/>
</head>
<body>
<div id="fb-root"></div>
<script>(function (d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s);
        js.id = id;
        js.src = "//connect.facebook.net/cs_CZ/sdk.js#xfbml=1&version=v2.9";
        fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));
</script>
<div>
    <form id="configurator" class="body" method="get">
        <input type="hidden" name="previousFormula" value="<?= $selectedFormula ?>">
        <div class="block">
            <div class="panel"><label>Formule:
                    <select id="formula" name="formula">
                        <?php
                        foreach (FormulaCode::getPossibleValues() as $formulaValue) {
                            ?>
                            <option value="<?= $formulaValue ?>"
                                    <?php if ($formulaValue === $selectedFormula->getValue()): ?>selected<?php endif ?>>
                                <?= FormulaCode::getIt($formulaValue)->translateTo('cs') ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <button type="submit">Vybrat</button>
            </div>
            <?php if ($selectedFormula): ?>
                <span class="panel forms">
                    (Forma: <?php
                    $forms = [];
                    foreach ($formulasTable->getForms($selectedFormula) as $formCode) {
                        $forms[] = $formCode->translateTo('cs');
                    }
                    echo implode(', ', $forms);
                    ?>)
                </span>
            <?php endif ?>
        </div>
        <?php if ($selectedFormula) { ?>
            <div id="modifiers" class="block">
                <div>Modifikátory:</div>
                <?php
                foreach ($formulasTable->getModifiers($selectedFormula) as $modifier) { ?>
                    <div class="modifier panel">
                        <label>
                            <input name="modifiers[<?= $modifier->getValue() ?>]" type="checkbox"
                                   value="<?= $modifier ?>"
                                   <?php if (array_key_exists($modifier->getValue(), $selectedModifierIndexes)): ?>checked<?php endif ?>>
                            <?= $modifier->translateTo('cs') ?>
                            <span class="forms">
                                <?php
                                $forms = [];
                                foreach ($modifiersTable->getForms($modifier) as $formCode) {
                                    $forms[] = $formCode->translateTo('cs');
                                }
                                if (count($forms) > 0) {
                                    echo '(Forma: ' . implode(', ', $forms) . ')';
                                } ?>
                            </span>
                        </label>
                        <?php
                        $createModifierInputIndex = function (array $modifiersChain) {
                            $wrapped = array_map(
                                function (string $chainPart) {
                                    return "[$chainPart]";
                                },
                                $modifiersChain
                            );

                            return implode($wrapped);
                        };
                        $showModifiers = function (string $currentModifierValue, array $selectedModifiers, array $inputNameParts)
                        use (&$showModifiers, $modifierCombinations, $createModifierInputIndex, $modifiersTable) {
                            if (array_key_exists($currentModifierValue, $selectedModifiers) && array_key_exists($currentModifierValue, $modifierCombinations)) {
                                /** @var array|string[] $selectedRelatedModifiers */
                                $selectedRelatedModifiers = $selectedModifiers[$currentModifierValue];
                                /** @var array|ModifierCode[][] $modifierCombinations */
                                foreach ($modifierCombinations[$currentModifierValue] as $possibleModifierValue => $possibleModifier) {
                                    $currentInputNameParts = $inputNameParts;
                                    $currentInputNameParts[] = $possibleModifierValue;
                                    ?>
                                    <div class="modifier">
                                        <label>
                                            <input name="modifiers<?= /** @noinspection PhpParamsInspection */
                                            $createModifierInputIndex($currentInputNameParts) ?>"
                                                   type="checkbox" value="<?= $possibleModifierValue ?>"
                                                   <?php if (array_key_exists($possibleModifierValue, $selectedRelatedModifiers)): ?>checked<?php endif ?>>
                                            <?= /** @var ModifierCode $possibleModifier */
                                            $possibleModifier->translateTo('cs') ?>
                                            <span class="forms">
                                            <?php
                                            $forms = [];
                                            foreach ($modifiersTable->getForms($possibleModifier) as $formCode) {
                                                $forms[] = $formCode->translateTo('cs');
                                            }
                                            if (count($forms) > 0) {
                                                echo '(Forma: ' . implode(', ', $forms) . ')';
                                            } ?>
                                            </span>
                                        </label>
                                        <?php $showModifiers($possibleModifierValue, $selectedRelatedModifiers, $currentInputNameParts) ?>
                                    </div>
                                <?php }
                            }
                        };
                        $showModifiers($modifier->getValue(), $selectedModifierIndexes, [$modifier->getValue()]); ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
        <button type="submit">Vybrat</button>
    </form>
</div>
<div class="footer">
    <?php
    $keysToModifiers = function (array $modifierNamesAsKeys) use (&$keysToModifiers) {
        $modifiers = [];
        foreach ($modifierNamesAsKeys as $modifierName => $childModifierNamesAsKeys) {
            $modifiers[] = ModifierCode::getIt($modifierName);
            if (is_array($childModifierNamesAsKeys)) {
                foreach ($keysToModifiers($childModifierNamesAsKeys) as $childModifier) {
                    $modifiers[] = $childModifier;
                }
            }
        }

        return $modifiers;
    };
    $selectedModifiers = $keysToModifiers($selectedModifierIndexes);
    $selectedSpellTraits = [];
    ?>
    <div>
        Sféra:
        <?php
        $realmOfModified = $formulasTable->getRealmOfModified($selectedFormula, $selectedModifiers, $selectedSpellTraits); ?>
        <ol class="realm" start="<?= $realmOfModified->getValue() ?>">
            <li></li>
        </ol>
    </div>
    <div>
        Náročnost: <?= $formulasTable->getDifficultyOfModified($selectedFormula, $selectedModifiers, $selectedSpellTraits) ?>
    </div>
    <div>
        <?php
        $affectionsOfModified = $formulasTable->getAffectionsOfModified($selectedFormula, $selectedModifiers, $selectedSpellTraits);
        if (count($affectionsOfModified) > 1):?>
            Náklonnosti:
        <?php else: ?>
            Náklonnost:
        <?php endif;
        $inCzech = [];
        /** @var Affection $affectionOfModified */
        foreach ($affectionsOfModified as $affectionOfModified) {
            $inCzech[] = $affectionOfModified->getAffectionPeriod()->translateTo('cs') . ' ' . $affectionOfModified->getValue();
        }
        echo implode(', ', $inCzech);
        ?>
    </div>
    <div>
        Vyvolání (příprava formule):
        <?php $evocationOfModified = $formulasTable->getEvocationOfModified($selectedFormula, $selectedModifiers, $selectedSpellTraits);
        $evocationTime = $evocationOfModified->getEvocationTime(Tables::getIt()->getTimeTable());
        $evocationUnitInCzech = $evocationTime->getUnitCode()->translateTo('cs', $evocationTime->getValue());
        echo ($evocationOfModified->getValue() >= 0 ? '+' : '')
            . "{$evocationOfModified->getValue()}  ({$evocationTime->getValue()} {$evocationUnitInCzech})";
        ?>
    </div>
    <div>
        Seslání (vypuštění kouzla):
        <?php $castingOfModified = $formulasTable->getCastingOfModified($selectedFormula, $selectedModifiers, $selectedSpellTraits);
        $castingBonus = $castingOfModified->getBonus();
        $castingUnitInCzech = $castingOfModified->getUnitCode()->translateTo('cs', $castingOfModified->getValue());
        echo ($castingBonus->getValue() >= 0 ? '+' : '')
            . "{$castingBonus->getValue()}  ({$castingOfModified->getValue()} {$castingUnitInCzech})";
        ?>
    </div>
    <div>
        Doba trvání:
        <?php $durationOfModified = $formulasTable->getDurationOfModified($selectedFormula, $selectedModifiers, $selectedSpellTraits);
        $durationTime = $durationOfModified->getDurationTime(Tables::getIt()->getTimeTable());
        $durationUnitInCzech = $durationTime->getUnitCode()->translateTo('cs', $durationTime->getValue());
        echo ($durationOfModified->getValue() >= 0 ? '+' : '')
            . "{$durationOfModified->getValue()}  ({$durationTime->getValue()} {$durationUnitInCzech})";
        ?>
    </div>
    <?php $radiusOfModified = $formulasTable->getRadiusOfModified($selectedFormula, $selectedModifiers, $selectedSpellTraits);
    if ($radiusOfModified !== null) { ?>
        <div>
            Poloměr:
            <?php $radiusDistance = $radiusOfModified->getDistance(Tables::getIt()->getDistanceTable());
            $radiusUnitInCzech = $radiusDistance->getUnitCode()->translateTo('cs', $radiusDistance->getValue());
            echo ($radiusOfModified->getValue() >= 0 ? '+' : '')
                . "{$radiusOfModified->getValue()} ({$radiusDistance->getValue()} {$radiusUnitInCzech})";
            ?>
        </div>
    <?php }
    $powerOfModified = $formulasTable->getPowerOfModified($selectedFormula, $selectedModifiers, $selectedSpellTraits);
    if ($powerOfModified !== null) { ?>
        <div>
            Síla:
            <?= ($powerOfModified->getValue() >= 0 ? '+' : '') . $powerOfModified->getValue(); ?>
        </div>
    <?php }
    $epicenterShiftOfModified = $formulasTable->getEpicenterShiftOfModified($selectedFormula, $selectedModifiers, $selectedSpellTraits);
    if ($epicenterShiftOfModified !== null) {
        $epicenterShiftDistance = $epicenterShiftOfModified->getDistance(Tables::getIt()->getDistanceTable());
        $epicenterShiftUnitInCzech = $epicenterShiftDistance->getUnitCode()->translateTo('cs', $epicenterShiftDistance->getValue());
        ?>
        <div>
            Posun transpozicí:
            <?= ($epicenterShiftOfModified->getValue() >= 0 ? '+' : '') .
            "{$epicenterShiftOfModified->getValue()} ({$epicenterShiftDistance->getValue()} {$epicenterShiftUnitInCzech})" ?>
        </div>
    <?php }
    $detailLevelOfModified = $formulasTable->getDetailLevelOfModified($selectedFormula, $selectedModifiers, $selectedSpellTraits);
    if ($detailLevelOfModified !== null) {
        ?>
        <div>
            Detailnost:
            <?= ($detailLevelOfModified->getValue() >= 0 ? '+' : '') . $detailLevelOfModified->getValue() ?>
        </div>
    <?php }
    $sizeChangeOfModified = $formulasTable->getSizeChangeOfModified($selectedFormula, $selectedModifiers, $selectedSpellTraits);
    if ($sizeChangeOfModified !== null) {
        ?>
        <div>
            Změna velikosti:
            <?= ($sizeChangeOfModified->getValue() >= 0 ? '+' : '') . $sizeChangeOfModified->getValue() ?>
        </div>
    <?php }
    $brightnessOfModified = $formulasTable->getBrightnessOfModified($selectedFormula, $selectedModifiers, $selectedSpellTraits);
    if ($brightnessOfModified !== null) {
        ?>
        <div>
            Jas:
            <?= ($brightnessOfModified->getValue() >= 0 ? '+' : '') . $brightnessOfModified->getValue() ?>
        </div>
    <?php }
    $spellSpeedOfModified = $formulasTable->getSpellSpeedOfModified($selectedFormula, $selectedModifiers, $selectedSpellTraits);
    if ($spellSpeedOfModified !== null) {
        $spellSpeed = $spellSpeedOfModified->getSpeed(Tables::getIt()->getSpeedTable());
        $spellSpeedUnitInCzech = $spellSpeed->getUnitCode()->translateTo('cs', $spellSpeed->getValue());
        ?>
        <div>
            Rychlost:
            <?= ($spellSpeedOfModified->getValue() >= 0 ? '+' : '') .
            "{$spellSpeedOfModified->getValue()} ({$spellSpeed->getValue()} {$spellSpeedUnitInCzech})" ?>
        </div>
    <?php }
    $attackOfModified = $formulasTable->getAttackOfModified($selectedFormula, $selectedModifiers, $selectedSpellTraits);
    if ($attackOfModified !== null) { ?>
        <div>
            Útočnost: <?= ($attackOfModified->getValue() >= 0 ? '+' : '') . $attackOfModified->getValue(); ?>
        </div>
    <?php }
    ?>
</div>
<div class="block">
    <div class="fb-like facebook"
         data-href="https://formule.theurg.drdplus.info/<?= $_SERVER['QUERY_STRING'] ? ('?' . $_SERVER['QUERY_STRING']) : '' ?>"
         data-layout="button" data-action="recommend"
         data-size="small" data-show-faces="false" data-share="true"></div>
    <a class="github-fork-ribbon right-bottom fixed"
       href="https://github.com/jaroslavtyc/drd-plus-theurgist-configurator/"
       title="Fork me on GitHub">Fork me</a>
</div>
</body>
</html>