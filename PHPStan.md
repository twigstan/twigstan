
## PHPStan limitations

### Using `--error-format=json` together with `--debug`, `--xdebug` or `--verbose` is corrupting the JSON

I noticed that it's very annoying to read the JSON from the output that also contains other debug statements.

Ideally, we can redirect the JSON separately to a file, instead of having it written to the output.

This can be solved by creating a custom ErrorFormatter that writes the JSON to a file. To do that, it's a bit hacky.
I used an ENV var to pass the location of the file. Ideally this becomes a core feature in PHPStan.
Something like: `--json-output-file=phpstan.json`

### Impossible to get the full AnalysisResult using the PHPStan CLI.

In the futurue I want to use data collectors in PHPStan and use that collected data in TwigStan.

To make it possible, I hacked this by creating a AnalysisResultToJson ErrorFormatter that serializes the full AnalysisResult
to a JSON file.

In TwigStan's AnalyzeCommand we call AnalysisResultsFromJsonReader to read the JSON and construct
it back into an AnalysisResult object.

Now we can use the Error objects and CollectedData objects from PHPStan inside TwigStan.

### When the extension-installer is installed by the end user, it automatically picks up
extensions like the strict rules extension.

For TwigStan, I want to disable this extension as it produces a lot more errors. Twig's PHP code is very simple.

To solve this, I remove `extension-installer/src/GeneratedConfig.php` before running TwigStan.
Afterwards I restore the file.

Ideally, we would have a way to disable extensions in config even though the extension-installer is used.

### No "path" structure type in parametersSchema

See tests/config.php.

We need this PHP configuration file because we have to use an absolute path.
There is currently no easy way for an extension to register something as a "path" parameter type.
The path should be relative to the config file it's referenced. Then made absolute.
It seems that PHPStan has a hardcoded list of these "path" parameter types:
https://github.com/phpstan/phpstan-src/blob/3175c81f26fd5bcb4a161b24e774921870ed2533/src/DependencyInjection/NeonAdapter.php#L122-L146
