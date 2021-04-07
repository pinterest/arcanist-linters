<?php
/**
 * Copyright 2020 Pinterest, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Lints GraphQL Schema Definition Language (SDL) files using graphql-schema-linter
 */
final class GraphQLSchemaLinter extends NodeExternalLinter {

  private $rules = array();
  private $configDirectory = null;
  private $customRulePaths = array();
  private $compatibilityOptions = array(
    'comment-descriptions' => false,
    'old-implements-syntax' => false,
  );
  private $ignore = null;

  public function getInfoName() {
    return 'GraphQLSchema';
  }

  public function getInfoURI() {
    return 'https://github.com/cjoudrey/graphql-schema-linter';
  }

  public function getInfoDescription() {
    return 'Validate GraphQL schema definitions against a set of rules';
  }

  public function getLinterName() {
    return 'GraphQL Schema Linter';
  }

  public function getLinterConfigurationName() {
    return 'graphql-schema';
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'graphql-schema.rules' => array(
        'type' => 'optional list<string>',
        'help' => pht('If specified, only these rules will be used to validate the schema.'),
      ),
      'graphql-schema.config' => array(
        'type' => 'optional string',
        'help' => pht('Use configuration from this directory (containing package.json, .graphql-schema-linterrc, or graphql-schema-linter.config.js) (https://github.com/cjoudrey/graphql-schema-linter#configuration-file)'),
      ),
      'graphql-schema.custom-rules' => array(
        'type' => 'optional list<string>',
        'help' => pht('Specify one or more paths containing custom rules'),
      ),
      'graphql-schema.ignore' => array(
        'type' => 'optional map<string, list<string>>',
        'help' => pht('Ignore errors for specific schema members. Keys should be rule names, values a list of paths to schema members (e.g. "Query.something.obvious")'),
      ),
      'graphql-schema.comment-descriptions' => array(
        'type' => 'optional bool',
        'help' => pht('Use old way of defining descriptions (with # comments) in GraphQL SDL'),
      ),
      'graphql-schema.old-implements-syntax' => array(
        'type' => 'optional bool',
        'help' => pht('Use old way of defining multiple implemented interfaces (with comma or space) in GraphQL SDL'),
      ),
    );
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'graphql-schema.rules':
        $this->rules = $value;
        return;
      case 'graphql-schema.config':
        $this->configDirectory = $value;
        return;
      case 'graphql-schema.custom-rules':
        $this->customRulePaths = $value;
        return;
      case 'graphql-schema.ignore':
        $this->ignore = $value;
        return;
      case 'graphql-schema.comment-descriptions':
        $this->compatibilityOptions['comment-descriptions'] = $value;
        return;
      case 'graphql-schema.old-implements-syntax':
        $this->compatibilityOptions['old-implements-syntax'] = $value;
        return;
    }
    return parent::setLinterConfigurationValue($key, $value);
  }

  public function getNodeBinary() {
    return 'graphql-schema-linter';
  }

  protected function getMandatoryFlags() {
    return array('--format=json');
  }

  protected function getDefaultFlags() {
    $flags = array();

    if (!empty($this->rules)) {
      $flags[] = '--rules='.implode(',', $this->rules);
    }
    if ($this->configDirectory) {
      $flags[] = '--config-directory='.$this->configDirectory;
    }
    if (!empty($this->customRulePaths)) {
      $flags[] = '--custom-rule-paths='.implode(' ', $this->customRulePaths);
    }
    if (!empty($this->ignore)) {
      $flags[] = '--ignore='.json_encode($this->ignore);
    }
    foreach ($this->compatibilityOptions as $flag => $enabled) {
      if ($enabled) {
        $flags[] = '--'.$flag;
      }
    }

    return $flags;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $messages = array();

    switch($err) {
      case 0: // If the process exits with 0 it means all rules passed.
        break;
      case 1: // if the process exits with 1 it means one or many rules failed.
        /* Sample Output
        {
          "errors": [
            {
              "message": "The object type `QueryRoot` is missing a description.",
              "location": {
                "line": 5,
                "column": 1,
                "file": "schema.graphql"
              },
              "rule": "types-have-descriptions"
            },
            {
              "message": "The field `QueryRoot.a` is missing a description.",
              "location": {
                "line": 6,
                "column": 3,
                "file": "schema.graphql"
              },
              "rule": "fields-have-descriptions"
            }
          ]
        }
        */

        $json = json_decode($stdout, true);
        $errors = $json['errors'];

        foreach ($errors as $error) {
          $message_parts = explode('. ', $error['message'], 2);
          $rule = $error['rule'];

          $message = new ArcanistLintMessage();
          $message->setPath($path)
            ->setCode($rule)
            ->setName($this->getLinterName())
            ->setLine($error['location']['line'])
            ->setChar($error['location']['column'])
            ->setDescription($error['message'])
            ->setSeverity($this->getDefaultMessageSeverity($rule));
          $messages[] = $message;
        }
        break;
      case 2: // If the process exits with 2 it means an invalid configuration was provided.
      case 3: // If the process exits with 3 it means an uncaught error happened.
        if ($stderr) {
          throw new Exception(pht("While reading %s, an error occurred:\n%s", $path, $stderr));
        }
        break;
      default:
        break;
    }

    return $messages;
  }

  protected function getDefaultMessageSeverity($rule) {
    switch($rule) {
      case 'graphql-syntax-error':
      case 'invalid-graphql-schema':
        return ArcanistLintSeverity::SEVERITY_ERROR;
      case 'arguments-have-descriptions':
      case 'deprecations-have-a-reason':
      case 'enum-values-have-descriptions':
      case 'fields-are-camel-cased':
      case 'fields-have-descriptions':
      case 'input-object-values-are-camel-cased':
      case 'input-object-values-have-descriptions':
      case 'types-have-descriptions':
        return ArcanistLintSeverity::SEVERITY_WARNING;
      case 'defined-types-are-used':
        return ArcanistLintSeverity::SEVERITY_ADVICE;
      case 'descriptions-are-capitalized':
      case 'enum-values-all-caps':
      case 'enum-values-sorted-alphabetically':
      case 'input-object-fields-sorted-alphabetically':
      case 'type-fields-sorted-alphabetically':
      case 'type-are-capitalized':
        // In theory these errors are autofixable,
        // but json-schema-linter doesn't provide us a replacement option
        return ArcanistLintSeverity::SEVERITY_WARNING;
      case 'relay-connection-types-spec':
      case 'relay-connection-arguments-spec':
      default:
        return ArcanistLintSeverity::SEVERITY_WARNING;
    }
  }
}
