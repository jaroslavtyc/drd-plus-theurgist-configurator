<?php
namespace DrdPlus\Theurgist\Configurator;

use DrdPlus\Theurgist\Codes\FormulaCode;
use DrdPlus\Theurgist\Codes\ModifierCode;
use DrdPlus\Theurgist\Formulas\FormulasTable;
use DrdPlus\Theurgist\Formulas\ModifiersTable;
use DrdPlus\Theurgist\Formulas\ProfilesTable;

require_once __DIR__ . '/vendor/autoload.php';

error_reporting(-1);
ini_set('display_errors', '1');

$formulasTable = new FormulasTable();
$selectedFormula = $_GET['formula'] ?? false;
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
$selectedModifiers = [];
if ($selectedFormula === $previouslySelectedFormula && !empty($_GET['modifiers'])) {
    $selectedModifiers = $buildModifiers((array)$_GET['modifiers']);
}
$modifierCombinations = [];
if (count($selectedModifiers) > 0) {
    $modifiersTable = new ModifiersTable();
    $profilesTable = new ProfilesTable();
    $buildPossibleModifiers = function (array $modifierValues) use (&$buildPossibleModifiers, $modifiersTable, $profilesTable) {
        $modifiers = [];
        foreach ($modifierValues as $modifierValue => $relatedModifierValues) {
            if (!array_key_exists($modifierValue, $modifiers)) { // otherwise skip already processed relating modifiers
                $modifierCode = ModifierCode::getIt($modifierValue);
                foreach ($modifiersTable->getProfiles($modifierCode) as $relatedProfileCode) {
                    foreach ($profilesTable->getModifiersForProfile($relatedProfileCode) as $relatedModifierCode) {
                        // by-related-modifier-indexed flat array
                        $modifiers[$modifierValue][$relatedModifierCode->getValue()] = $relatedModifierCode;
                    }
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
    $modifierCombinations = $buildPossibleModifiers($selectedModifiers);
}
?>
<!DOCTYPE html>
<html lang="cs" xmlns="http://www.w3.org/1999/html">
<head>
    <title>Formule pro DrD+ theurga</title>
    <meta http-equiv="Content-type" content="text/html;charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <script src="js/main.js"></script>
</head>
<body>
<form id="configurator" method="get">
    <input type="hidden" name="previousFormula" value="<?= $selectedFormula ?>">
    <div>
        <label>Formule:
            <select id="formula" name="formula">
                <?php
                foreach (FormulaCode::getPossibleValues() as $formulaValue) {
                    ?>
                    <option value="<?= $formulaValue ?>"
                            <?php if ($formulaValue === $selectedFormula): ?>selected<?php endif ?>>
                        <?= FormulaCode::getIt($formulaValue)->translateTo('cs') ?>
                    </option>
                <?php } ?>
            </select>
        </label>
        <button type="submit">Vybrat</button>
    </div>
    <?php if ($selectedFormula !== false) {

        ?>
        <div id="modifiers">
            <div>Modifikátory:</div>
            <?php
            foreach ($formulasTable->getModifiers(FormulaCode::getIt($selectedFormula)) as $modifier) { ?>
                <div class="modifier direct">
                    <label>
                        <input name="modifiers[<?= $modifier->getValue() ?>]" type="checkbox" value="<?= $modifier ?>"
                               <?php if (array_key_exists($modifier->getValue(), $selectedModifiers)): ?>checked<?php endif ?>>
                        <?= $modifier->translateTo('cs') ?>
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
                    $showModifiers = function (string $currentModifierValue, array $selectedModifiers, array $inputNameParts) use (&$showModifiers, $modifierCombinations, $createModifierInputIndex) {
                        if (array_key_exists($currentModifierValue, $selectedModifiers) && array_key_exists($currentModifierValue, $modifierCombinations)) {
                            /** @var array|string[] $selectedRelatedModifiers */
                            $selectedRelatedModifiers = $selectedModifiers[$currentModifierValue];
                            foreach ($modifierCombinations[$currentModifierValue] as $possibleModifierValue => $possibleModifier) {
                                $currentInputNameParts = $inputNameParts;
                                $currentInputNameParts[] = $possibleModifierValue;
                                ?>
                                <div class="modifier">
                                    <label>
                                        <input name="modifiers<?= $createModifierInputIndex($currentInputNameParts) ?>"
                                               type="checkbox" value="<?= $possibleModifierValue ?>"
                                               <?php if (array_key_exists($possibleModifierValue, $selectedRelatedModifiers)): ?>checked<?php endif ?>>
                                        <?= /** @var ModifierCode $possibleModifier */
                                        $possibleModifier->translateTo('cs') ?>
                                    </label>
                                    <?php $showModifiers($possibleModifierValue, $selectedRelatedModifiers, $currentInputNameParts) ?>
                                </div>
                            <?php }
                        }
                    };
                    $showModifiers($modifier->getValue(), $selectedModifiers, [$modifier->getValue()]); ?>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
    <button type="submit">Vybrat</button>
</form>
</body>
</html>