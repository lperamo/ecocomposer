<?php
declare(strict_types=1);

const
  ARG_MODE = 1,
  CLI_BASE = "\e[38;2;190;190;190m",
  CLI_ERROR = "\e[38;2;255;100;100m",
  CLI_INFO_HIGHLIGHT = "\e[38;2;100;200;200m",
  END_COLOR = "\e[0m",
  JSON_CONFIG_PATH = __DIR__ . '/ecoComposer.json',
  TEMP_ARCHIVE_PATH = 'ecocomposer.tar.gz';

define('MODE', $argv[ARG_MODE]);

if ($argv[ARG_MODE] !== 'i' && $argv[ARG_MODE] !== 'u')
  exit(1);

if (!file_exists(JSON_CONFIG_PATH))
{
  echo CLI_ERROR, 'The file ', CLI_INFO_HIGHLIGHT, JSON_CONFIG_PATH, CLI_ERROR, ' is missing!', END_COLOR, PHP_EOL;
  die;
}

$jsonConfig = file_get_contents(JSON_CONFIG_PATH);
$decodedJsonConfig = json_decode($jsonConfig, true);
$jsonLastError = json_last_error();
echo CLI_BASE, 'Decoding the JSON config ', CLI_INFO_HIGHLIGHT, JSON_CONFIG_PATH, CLI_BASE, '...', PHP_EOL;

if ($jsonLastError !== JSON_ERROR_NONE)
{
  echo CLI_ERROR,
    [
      JSON_ERROR_DEPTH => 'Maximum stack depth exceeded.',
      JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch.',
      JSON_ERROR_CTRL_CHAR => 'Unexpected control character found.',
      JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON.',
      JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded.'
    ][$jsonLastError], END_COLOR, PHP_EOL;
  die;
}

if (empty($decodedJsonConfig))
{
  echo CLI_ERROR, 'The JSON config ', CLI_INFO_HIGHLIGHT, JSON_CONFIG_PATH, CLI_ERROR, ' is empty.', END_COLOR, PHP_EOL;
  die;
}

if (!isset($decodedJsonConfig['licenseKey']))
{
  echo CLI_ERROR, 'The JSON config ', CLI_INFO_HIGHLIGHT, JSON_CONFIG_PATH, CLI_ERROR, ' does not contain a ',
    CLI_INFO_HIGHLIGHT, 'licenseKey', CLI_ERROR, ' key. Checks the reference file ', CLI_INFO_HIGHLIGHT,
    'ecoComposer.json.dist', CLI_ERROR, ' for more information.', END_COLOR, PHP_EOL;
  die;
}

if (!isset($decodedJsonConfig['components']) && !isset($decodedJsonConfig['utils']))
{
  echo CLI_ERROR, 'The JSON config ', CLI_INFO_HIGHLIGHT, JSON_CONFIG_PATH, CLI_ERROR,
    ' does not contain any components or utilities. Checks the reference file ', CLI_INFO_HIGHLIGHT,
    'ecoComposer.json.dist', CLI_ERROR, ' for more information.', END_COLOR, PHP_EOL;
  die;
}

$ch = curl_init();
curl_setopt_array(
  $ch,
  [
    CURLOPT_HEADER => false,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $jsonConfig,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_URL => 'https://ecocomposer.otra.tech/eco-api',
  ]
);

$content = curl_exec($ch);
$error = curl_error($ch);

$filePointer = fopen(__DIR__ . '/ecocomposer.tar.gz', 'w');
fwrite($filePointer, $content);
fclose($filePointer);
curl_close($ch);

if (curl_errno($ch))
  echo CLI_ERROR, 'Curl error : ', curl_error($ch), END_COLOR, PHP_EOL;
else
{
  try
  {
    $phar = new PharData(TEMP_ARCHIVE_PATH);

    // If it is an installation, we just extract the files normally, otherwise we overwrite the existing files.
    $phar->extractTo('.', null, MODE !== 'i');
    unlink(TEMP_ARCHIVE_PATH);
    echo CLI_BASE, 'EcoComposer downloaded successfully', "\e[38;2;100;200;100m", ' ✔', END_COLOR, PHP_EOL;
  } catch (Exception $e)
  {
    echo CLI_ERROR, $e->getMessage(), END_COLOR, PHP_EOL;
  }
}
