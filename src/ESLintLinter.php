<?php

final class ESLintLinter extends ArcanistExternalLinter {
  const ESLINT_WARNING = '1';
  const ESLINT_ERROR = '2';

  private $flags = array();

  public function getInfoName() {
    return 'ESLint';
  }

  public function getInfoURI() {
    return 'https://eslint.org/';
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

  public function getVersion() {
    list($err, $stdout, $stderr) = exec_manual('%C -v', $this->getExecutableCommand());

    $matches = array();
    if (preg_match('/^v(\d\.\d\.\d)$/', $stdout, $matches)) {
      return $matches[1];
    } else {
      return false;
    }
  }

  protected function getMandatoryFlags() {
    return array(
      '--format=json',
      '--no-color',
    );
  }

  protected function getDefaultFlags() {
    return $this->flags;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'eslint.config' => array(
        'type' => 'optional string',
        'help' => pht('Use configuration from this file or shareable config. (https://eslint.org/docs/user-guide/command-line-interface#-c---config)'),
      ),
      'eslint.env' => array(
        'type' => 'optional string',
        'help' => pht('Specify environments. To specify multiple environments, separate them using commas. (https://eslint.org/docs/user-guide/command-line-interface#--env)'),
      ),
    );
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'eslint.config':
        $this->flags[] = '--config ' . $value;
        return;
      case 'eslint.env':
        $this->flags[] = '--env ' . $value;
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
    if ($err) {
      return;
    }

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
