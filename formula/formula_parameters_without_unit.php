<?php
namespace DrdPlus\Theurgist\Configurator;

use DrdPlus\Theurgist\Codes\FormulaCode;
use DrdPlus\Theurgist\Codes\FormulaMutableSpellParameterCode;
use DrdPlus\Theurgist\Spells\FormulasTable;
use DrdPlus\Theurgist\Spells\SpellParameters\Partials\IntegerCastingParameter;
use Granam\String\StringTools;

/** @var FormulaCode $selectedFormulaCode */
/** @var FormulasTable $formulasTable */
/** @var IndexController $controller */

$formulaParametersWithoutUnit = [
    FormulaMutableSpellParameterCode::ATTACK,
    FormulaMutableSpellParameterCode::BRIGHTNESS,
    FormulaMutableSpellParameterCode::POWER,
    FormulaMutableSpellParameterCode::DETAIL_LEVEL,
    FormulaMutableSpellParameterCode::SIZE_CHANGE,
];
foreach ($formulaParametersWithoutUnit as $parameterName) {
    $getParameter = StringTools::assembleGetterForName($parameterName);
    /** @var IntegerCastingParameter $parameter */
    $parameter = $formulasTable->$getParameter($selectedFormulaCode);
    if ($parameter === null) {
        continue;
    }
    $parameterCode = FormulaMutableSpellParameterCode::getIt($parameterName);
    ?>
    <div class="parameter panel">
        <label><?= $parameterCode->translateTo('cs') ?>:
            <?php
            $parameterAdditionByDifficulty = $parameter->getAdditionByDifficulty();
            $additionStep = $parameterAdditionByDifficulty->getAdditionStep();
            $optionParameterValue = $parameter->getDefaultValue(); // from the lowest
            $parameterDifficultyChange = $parameterAdditionByDifficulty->getCurrentDifficultyIncrement();
            $optionParameterChange = 0;
            $previousOptionParameterValue = null;
            $selectedParameterValue = $controller->getSelectedFormulaSpellParameters()[$parameterName] ?? false;
            ?>
            <select name="formulaParameters[<?= $parameterName ?>]">
                <?php
                do {
                    if ($previousOptionParameterValue === null || $previousOptionParameterValue < $optionParameterValue) { ?>
                        <option value="<?= $optionParameterValue ?>"
                                <?php if ($selectedParameterValue !== false && $selectedParameterValue === $optionParameterValue){ ?>selected<?php } ?>>
                            <?= ($optionParameterValue >= 0 ? '+' : '')
                            . "{$optionParameterValue} [{$parameterDifficultyChange}]"; ?>
                        </option>
                    <?php }
                    $previousOptionParameterValue = $optionParameterValue;
                    $optionParameterValue++;
                    $optionParameterChange++;
                    $parameter = $parameter->getWithAddition($optionParameterChange);
                    $parameterAdditionByDifficulty = $parameter->getAdditionByDifficulty();
                    $parameterDifficultyChange = $parameterAdditionByDifficulty->getCurrentDifficultyIncrement();
                } while ($additionStep > 0 /* at least once even on no addition possible */ && $parameterDifficultyChange < 21) ?>
            </select>
        </label>
    </div>
<?php } ?>