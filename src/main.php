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

    $otherDescription = $inputs['otherDescription'] ?? '';
    
    $inputs = [
        "income" => $inputs['income'] ?? 0,

        "rent"      => $inputs['rent']      ?? 0,
        "utilities" => $inputs['utilities'] ?? 0,

        "food"           => $inputs['food']           ?? 0,
        "gas"            => $inputs['gas']            ?? 0,
        "entertainment"  => $inputs['entertainment']  ?? 0,
        "clothes"        => $inputs['clothes']        ?? 0,
        "schoolSupplies" => $inputs['schoolSupplies'] ?? 0,
        "moneyForFamily" => $inputs['moneyForFamily'] ?? 0,
        "unplanned"      => $inputs['unplanned']      ?? 0,
        "cc"             => $inputs['cc']             ?? 0,
        "other"          => $inputs['other']          ?? 0,

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
        $otherDescription = yield readline("     describe `other` ($otherDescription): ");
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

    $periodReadable = $period->format("Y F");
    
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
            > **Info** Overall the budged is fine.<br/>
            > The highest expense you had this month was {$highest['key']} with a value of {$highest['value']}.<br/>
            > <br/>
            > Remaining: {$remaining}.
            HTML;
    } else {
        $status = <<<HTML
            > **Warning** Overall the budged is not good.<br/>
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
        "otherDescription" => $otherDescription,

        "date" => $originalPeriod,
    ];

    $inputs = json_encode($inputs, JSON_PRETTY_PRINT);

    file_put_contents("$directory/$workspace/$previousInputsFileName", $inputs);

    $inputsForMarkdown = preg_replace('/^/m', '> ', $inputs);

    file_put_contents("$directory/$workspace/$periodReadable.md", <<<HTML
        ## Budget of $periodReadable

        ## TLDR

        $status

        ### Expenses

            - Rent {$expenses['rent']}
            - Utilities {$expenses['utilities']}
            - Food {$expenses['food']}
            - Gas {$expenses['gas']}
            - Entertainment {$expenses['entertainment']}
            - Clothes {$expenses['clothes']}
            - School Supplies {$expenses['schoolSupplies']}
            - Money for Family {$expenses['moneyForFamily']}
            - Unplanned {$expenses['unplanned']}
            - Credit Card Expenses {$expenses['cc']}
            - Other  {$expenses['other']}<br/>
              > **Note** {$otherDescription}

        ---

        > **Note** inputs
        > ```json
        $inputsForMarkdown
        > ```
        HTML);
        
    if ($then) {
        $then = StringExpansion::variable($then, [
            "relativeFileName"  => escapeshellarg("$workspace/$periodReadable.md"),
            "workspace"         => $workspace,
            "inputsForMarkdown" => escapeshellarg($inputsForMarkdown),

            "income" => escapeshellarg($income),

            "rent"      => escapeshellarg($expenses['rent']),
            "utilities" => escapeshellarg($expenses['utilities']),

            "food"             => escapeshellarg($expenses['food']),
            "gas"              => escapeshellarg($expenses['gas']),
            "entertainment"    => escapeshellarg($expenses['entertainment']),
            "clothes"          => escapeshellarg($expenses['clothes']),
            "schoolSupplies"   => escapeshellarg($expenses['schoolSupplies']),
            "moneyForFamily"   => escapeshellarg($expenses['moneyForFamily']),
            "unplanned"        => escapeshellarg($expenses['unplanned']),
            "cc "              => escapeshellarg($expenses['cc']),
            "other"            => escapeshellarg($expenses['other']),
            "otherDescription" => escapeshellarg($otherDescription),

            "date" => escapeshellarg($originalPeriod),
        ]);

        echo yield execute($then);
    }
}
