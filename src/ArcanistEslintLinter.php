<?php

final class ArcanistEslintLinter extends ArcanistExternalLinter {
  const ESLINT_WARNING = '1';
  const ESLINT_ERROR = '2';

  public function getInfoName() {
    return 'Eslint';
  }

  public function getInfoURI() {
    return 'http://eslint.org/';
  }

  public function getInfoDescription() {
    return pht('The pluggable linting utility for JavaScript and JSX');
  }

  public function getLinterName() {
    return 'ESLINT';
  }

  public function getLinterConfigurationName() {
    return 'eslint';
  }

  public function getDefaultBinary() {
    return 'eslint';
  }

  protected function getMandatoryFlags() {
    return array(
      '--format=json',
      '--no-color',
    );
  }

  public function getVersion() {
    list($err, $stdout, $stderr) = exec_manual('%C -v', $this->getExecutableCommand());

    return $err
      ? false
      : $stdout;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'eslint.bin' => array(
        'type' => 'optional string',
        'help' => pht('Location of eslint executable. Default: (eslint)'),
      ),
    );
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'eslint.bin':
        $this->setBinary($value);
        return;
    }
    return parent::setLinterConfigurationValue($key, $value);
  }

  public function getInstallInstructions() {
    return pht(
      'run `%s` to install eslint globally, or `%s` to add it to your project.',
      'npm install --global eslint',
      'npm install --save-dev eslint'
    );
  }

  protected function canCustomizeLintSeverities() {
    return false;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $json = json_decode($stdout, true);
    $messages = array();

    foreach ($json as $file) {
      foreach ($file['messages'] as $offense) {
        $message = new ArcanistLintMessage();
        $message->setPath($file['filePath']);
        $message->setSeverity($this->mapSeverity($offense['severity']));
        $message->setName($offense['ruleId']);
        $message->setDescription($offense['message']);
        $message->setLine($offense['line']);
        $message->setChar($offense['column']);
        $message->setCode($offense['source']);
        $messages[] = $message;
      }
    }

    return $messages;
  }

  private function mapSeverity($eslintSeverity) {
    switch($eslintSeverity) {
      case '0':
      case '1':
        return ArcanistLintSeverity::SEVERITY_WARNING;
      case '2':
      default:
        return ArcanistLintSeverity::SEVERITY_ERROR;
    }
  }
}
