<?php



use function Amp\File\read;
use Amp\Loop;

use CatPaw\Attributes\Option;
use function CatPaw\Store\readable;

function main(
    #[Option("--input-file")] string $input_file,
    #[Option("--output-file")] string $output_file,
) {
    $budget = readable('', fn (callable $set) => Loop::repeat(1000, fn () => $set(yaml_parse(yield read($input_file)))));

    $budget->subscribe(function(string $contents) {
    });
}
