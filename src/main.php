<?php

use CatPaw\Attributes\Option;

use function CatPaw\execute;
use function CatPaw\readline;
use CatPaw\Utilities\StandardDateFormat;
use CatPaw\Utilities\StandardDateParse;
use CatPaw\Utilities\StringExpansion;

function main(
    #[Option("--then")] string|false $then,
    #[Option("--workspace")] string $workspace = 'budget',
) {
    $directory = realpath('./');

    $previousInputsFileName = '.previous-inputs.json';
    if (is_file("$directory/$workspace/$previousInputsFileName")) {
        $previousInputs = file_get_contents("$directory/$workspace/$previousInputsFileName");
    } else {
        $previousInputs = false;
    }

    $inputs = $previousInputs?json_decode($previousInputs, true):[];
    
    $inputs = [
        "income" => $inputs['income'] ?? 0,

        "rent"      => $inputs['rent']      ?? 0,
        "utilities" => $inputs['utilities'] ?? 0,

        "food"             => $inputs['food']             ?? 0,
        "gas"              => $inputs['gas']              ?? 0,
        "entertainment"    => $inputs['entertainment']    ?? 0,
        "clothes"          => $inputs['clothes']          ?? 0,
        "schoolSupplies"   => $inputs['schoolSupplies']   ?? 0,
        "moneyForFamily"   => $inputs['moneyForFamily']   ?? 0,
        "unplanned"        => $inputs['unplanned']        ?? 0,
        "cc"               => $inputs['cc']               ?? 0,
        "other"            => $inputs['other']            ?? 0,
        "otherDescription" => $inputs['otherDescription'] ?? '',

        "period" => $inputs['period'] ?? false,
    ];


    $defaultPeriod = $inputs['period']?$inputs['period']:StandardDateFormat::YYYYMM(time(), '-');
    
    $expenses = [];

    /** @var string */
    $originalPeriod = yield readline("This budget is for ($defaultPeriod): ", fn ($value) => '' === $value || StandardDateParse::YYYYMM($value, '-')?true:'Date must use the format YYYY-MM.');

    if (!$originalPeriod) {
        $originalPeriod = $defaultPeriod;
    }

    /** @var DateTime */
    $period = StandardDateParse::YYYYMM($originalPeriod, '-');

    
    echo "What are your basic expenses this month?\n";
    $expenses['rent']      = yield readline("Bills that are the same each month, like rent ({$inputs['rent']}): ");
    $expenses['rent']      = '' !== $expenses['rent']?(float)$expenses['rent']:(float)$inputs['rent'];
    $expenses['utilities'] = yield readline("Bills that might change each month, like utilities ({$inputs['utilities']}): ");
    $expenses['utilities'] = '' !== $expenses['utilities']?(float)$expenses['utilities']:(float)$inputs['utilities'];

    echo "What other expenses do you have this month?\n";
    $expenses['food'] = yield readline("   - food ({$inputs['food']}): ");
    $expenses['food'] = '' !== $expenses['food']?(float)$expenses['food']:(float)$inputs['food'];
    $expenses['gas']  = yield readline("   - gas ({$inputs['gas']}): ");
    $expenses['gas']  = '' !== $expenses['gas']?(float)$expenses['gas']:(float)$inputs['gas'];

    $expenses['entertainment']  = yield readline("   - entertainment ({$inputs['entertainment']}): ");
    $expenses['entertainment']  = '' !== $expenses['entertainment']?(float)$expenses['entertainment']:(float)$inputs['entertainment'];
    $expenses['clothes']        = yield readline("   - clothes ({$inputs['clothes']}): ");
    $expenses['clothes']        = '' !== $expenses['clothes']?(float)$expenses['clothes']:(float)$inputs['clothes'];
    $expenses['schoolSupplies'] = yield readline("   - school supplies ({$inputs['schoolSupplies']}): ");
    $expenses['schoolSupplies'] = '' !== $expenses['schoolSupplies']?(float)$expenses['schoolSupplies']:(float)$inputs['schoolSupplies'];
    $expenses['moneyForFamily'] = yield readline("   - money for family ({$inputs['moneyForFamily']}): ");
    $expenses['moneyForFamily'] = '' !== $expenses['moneyForFamily']?(float)$expenses['moneyForFamily']:(float)$inputs['moneyForFamily'];
    $expenses['unplanned']      = yield readline("   - unplanned expenses, like a car repair or medical bills ({$inputs['unplanned']}): ");
    $expenses['unplanned']      = '' !== $expenses['unplanned']?(float)$expenses['unplanned']:(float)$inputs['unplanned'];
    $expenses['cc']             = yield readline("   - credit card bills ({$inputs['cc']}): ");
    $expenses['cc']             = '' !== $expenses['cc']?(float)$expenses['cc']:(float)$inputs['cc'];
    $expenses['other']          = yield readline("   - other ({$inputs['other']}): ");
    $expenses['other']          = '' !== $expenses['other']?(float)$expenses['other']:(float)$inputs['other'];

    if ($expenses['other'] > 0) {
        $otherDescription           = yield readline("     describe `other` ({$inputs['otherDescription']}): ");
        $inputs['otherDescription'] = '' !== $otherDescription?$otherDescription:$inputs['otherDescription'];
    }

    $income = yield readline("How much money did you make last month? ({$inputs['income']}) ");
    $income = '' !== $income?(float)$income:(float)$inputs['income'];

    $remaining = $income;
    foreach ($expenses as $key => $value) {
        $remaining -= $value;
    }

    if (!is_dir("$directory/$workspace")) {
        mkdir("$directory/$workspace", 0777, true);
    }
    
    $highest = [
        'key'   => 'none',
        'value' => .0,
    ];

    foreach ($expenses as $key => $value) {
        if ($highest['value'] < $value) {
            $highest = [
                'key'   => $key,
                'value' => $value,
            ];
        }
    }


    if ($remaining > 0) {
        $status = <<<HTML
            > **Note** ## Overall the budged is fine.<br/>
            > The highest expense you had this month was {$highest['key']} with a value of {$highest['value']}.<br/>
            > <br/>
            > Remaining: {$remaining}.
            HTML;
    } else {
        $status = <<<HTML
            > **Warning** ## Overall the budged is not good.<br/>
            > The highest expense you had this month was {$highest['key']} with a value of {$highest['value']}.<br/>
            > <br/>
            > Remaining: {$remaining}.
            HTML;
    }

    $inputs = [
        "income" => $income,

        "rent"      => $expenses['rent'],
        "utilities" => $expenses['utilities'],

        "food"             => $expenses['food'],
        "gas"              => $expenses['gas'],
        "entertainment"    => $expenses['entertainment'],
        "clothes"          => $expenses['clothes'],
        "schoolSupplies"   => $expenses['schoolSupplies'],
        "moneyForFamily"   => $expenses['moneyForFamily'],
        "unplanned"        => $expenses['unplanned'],
        "cc "              => $expenses['cc'],
        "other"            => $expenses['other'],
        "otherDescription" => $inputs['otherDescription'],

        "date" => $originalPeriod,
    ];

    file_put_contents("$directory/$workspace/$previousInputsFileName", json_encode($inputs, JSON_PRETTY_PRINT));

    $incomeSign    = 0 === $income?'':($income > 0?'+':'-');
    $remainingSign = 0 === $remaining?'':($remaining > 0?'+':'-');

    $mdVars = [
        "status"           => $remaining > 0?'✅':'❌',
        "period"           => $period->format("Y F"),
        "rent"             => $expenses['rent']           > 0?"\$\color{red}{\\textsf{-{$expenses['rent']}}}\$":"\$\color{green}{\\textsf{{$expenses['rent']}}}\$",
        "utilities"        => $expenses['utilities']      > 0?"\$\color{red}{\\textsf{-{$expenses['utilities']}}}\$":"\$\color{green}{\\textsf{{$expenses['utilities']}}}\$",
        "food"             => $expenses['food']           > 0?"\$\color{red}{\\textsf{-{$expenses['food']}}}\$":"\$\color{green}{\\textsf{{$expenses['food']}}}\$",
        "gas"              => $expenses['gas']            > 0?"\$\color{red}{\\textsf{-{$expenses['gas']}}}\$":"\$\color{green}{\\textsf{{$expenses['gas']}}}\$",
        "entertainment"    => $expenses['entertainment']  > 0?"\$\color{red}{\\textsf{-{$expenses['entertainment']}}}\$":"\$\color{green}{\\textsf{{$expenses['entertainment']}}}\$",
        "clothes"          => $expenses['clothes']        > 0?"\$\color{red}{\\textsf{-{$expenses['clothes']}}}\$":"\$\color{green}{\\textsf{{$expenses['clothes']}}}\$",
        "schoolSupplies"   => $expenses['schoolSupplies'] > 0?"\$\color{red}{\\textsf{-{$expenses['schoolSupplies']}}}\$":"\$\color{green}{\\textsf{{$expenses['schoolSupplies']}}}\$",
        "moneyForFamily"   => $expenses['moneyForFamily'] > 0?"\$\color{red}{\\textsf{-{$expenses['moneyForFamily']}}}\$":"\$\color{green}{\\textsf{{$expenses['moneyForFamily']}}}\$",
        "unplanned"        => $expenses['unplanned']      > 0?"\$\color{red}{\\textsf{-{$expenses['unplanned']}}}\$":"\$\color{green}{\\textsf{{$expenses['unplanned']}}}\$",
        "cc"               => $expenses['cc']             > 0?"\$\color{red}{\\textsf{-{$expenses['cc']}}}\$":"\$\color{green}{\\textsf{{$expenses['cc']}}}\$",
        "other"            => $expenses['other']          > 0?"\$\color{red}{\\textsf{-{$expenses['other']}}}\$":"\$\color{green}{\\textsf{{$expenses['other']}}}\$",
        "otherDescription" => $expenses['other']          > 0 ?<<<HTML
            > **Note** `Other` description<br/>
            > {$inputs['otherDescription']}
            HTML:'',
        "income"    => $income    > 0?"\$\color{green}{\\textsf{ $incomeSign$income }}\$":"\$\color{red}{\\textsf{ $incomeSign$income }}\$",
        "remaining" => $remaining > 0?"\$\color{green}{\\textsf{ $remainingSign$remaining }}\$":"\$\color{red}{\\textsf{ $remainingSign$remaining }}\$",
    ];

    file_put_contents("$directory/$workspace/{$mdVars['period']}.md", <<<HTML
        ## Budget of {$mdVars['period']} {$mdVars['status']}

        | Description | Modifier | 
        | :---------- | -------: |
        | Income        | {$mdVars['income']} |
        | Rent        | {$mdVars['rent']} |
        | Utilities   | {$mdVars['utilities']} |
        | Food        | {$mdVars['food']} |
        | Gas         | {$mdVars['gas']} |
        | Entertainment | {$mdVars['entertainment']} |
        | Clothes       | {$mdVars['clothes']} |
        | School  Supplies | {$mdVars['schoolSupplies']} |
        | Money for Family | {$mdVars['moneyForFamily']} |
        | Unplanned        | {$mdVars['unplanned']} |
        | Credit Card Expenses | {$mdVars['cc']} |
        | Other                |  {$mdVars['other']} |

        {$mdVars['otherDescription']}

        | Remaining | | 
        | :---------- | -------: |
        | Net       | {$mdVars['remaining']} |
        HTML);

        
        
    if ($then) {
        echo 'Executing --then...'.PHP_EOL;
        $then = StringExpansion::variable($then, [
            "relativeFileName" => escapeshellarg("$workspace/{$mdVars['period']}.md"),

            "income" => $income,

            "rent"      => $expenses['rent'],
            "utilities" => $expenses['utilities'],

            "food"             => $expenses['food'],
            "gas"              => $expenses['gas'],
            "entertainment"    => $expenses['entertainment'],
            "clothes"          => $expenses['clothes'],
            "schoolSupplies"   => $expenses['schoolSupplies'],
            "moneyForFamily"   => $expenses['moneyForFamily'],
            "unplanned"        => $expenses['unplanned'],
            "cc "              => $expenses['cc'],
            "other"            => $expenses['other'],
            "otherDescription" => escapeshellarg($inputs['otherDescription']),

            "date" => $originalPeriod,
        ]);

        echo yield execute($then);
    }
}
