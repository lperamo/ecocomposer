#!usr/bin/php -ddisplay_errors=E_ALL
<?php
declare(strict_types=1);

const
  ARG_MODE = 1,
  CLI_BASE = "\e[38;2;190;190;190m",
  CLI_ERROR = "\e[38;2;255;100;100m",
  CLI_INFO_HIGHLIGHT = "\e[38;2;100;200;200m",
  CLI_WARNING = "\e[38;2;190;190;100m",
  END_COLOR = "\e[0m";

define('MODE', $argv[ARG_MODE] ?? 'i');
define('JSON_CONFIG_PATH', $_SERVER['PWD'] . '/ecoComposer.json');
define('TEMP_ARCHIVE_PATH', $_SERVER['PWD'] . '/ecocomposer.tar.gz');

const LABEL_THE_JSON_CONFIG = 'The JSON config ' . CLI_INFO_HIGHLIGHT . JSON_CONFIG_PATH . CLI_ERROR;

if (MODE !== 'i' && MODE !== 'u')
  exit(1);

if (!file_exists(JSON_CONFIG_PATH))
{
  echo CLI_ERROR, 'The file ', CLI_INFO_HIGHLIGHT, JSON_CONFIG_PATH, CLI_ERROR, ' is missing!', END_COLOR, PHP_EOL;
  exit(1);
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
  exit(1);
}

if (empty($decodedJsonConfig))
{
  echo CLI_ERROR, LABEL_THE_JSON_CONFIG, ' is empty.', END_COLOR, PHP_EOL;
  exit(1);
}

if (!isset($decodedJsonConfig['licenseKey']))
{
  echo CLI_ERROR, LABEL_THE_JSON_CONFIG, ' does not contain a ', CLI_INFO_HIGHLIGHT, 'licenseKey', CLI_ERROR,
    ' key. Checks the reference file ', CLI_INFO_HIGHLIGHT, 'ecoComposer.json.dist', CLI_ERROR,
    ' for more information.', END_COLOR, PHP_EOL;
  exit(1);
}

if (!isset($decodedJsonConfig['components']) && !isset($decodedJsonConfig['utils']))
{
  echo CLI_ERROR, LABEL_THE_JSON_CONFIG, ' does not contain any components or utilities. Checks the reference file ',
    CLI_INFO_HIGHLIGHT, 'ecoComposer.json.dist', CLI_ERROR, ' for more information.', END_COLOR, PHP_EOL;
  exit(1);
}

$ch = curl_init();
curl_setopt_array(
  $ch,
  [
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $jsonConfig,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_URL => 'https://ecocomposer.otra.tech/eco-api',
  ]
);

$content = curl_exec($ch);
$responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$error = curl_error($ch);
curl_close($ch);

if (curl_errno($ch))
  echo CLI_ERROR, 'Curl error : ', curl_error($ch), END_COLOR, PHP_EOL;
else
{
  if ($responseCode === 404)
  {
    echo CLI_WARNING, 'You may have a problem with your license key number. Please check it out.', END_COLOR, PHP_EOL;
    exit(1);
  }

  $filePointer = fopen(TEMP_ARCHIVE_PATH, 'w');
  fwrite($filePointer, $content);
  fclose($filePointer);

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
