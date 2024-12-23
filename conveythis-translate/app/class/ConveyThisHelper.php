<?php
require_once 'ConveyThis.php';

class ConveyThisHelper 
{
    private static $instance;
    private $conveyThis;
    private $variables;

    private function __construct()
    {
        $this->conveyThis = ConveyThis::Instance();
        $this->variables = $this->conveyThis->getVariables();
    }

    public static function __callStatic($name, $arguments)
    {
        if (!(self::$instance instanceof self)) {
             self::$instance = new static();
        }

        if (method_exists(self::$instance, $name)) {
            return call_user_func_array([self::$instance, $name], $arguments);
        }

        throw new \BadMethodCallException("Method $name not found in class " . __CLASS__);
    }

    private function getApiKey()
    {
        return $this->variables->getApiKey();
    }

    private function getSourceLanguage()
    {
        return $this->variables->getSourceLanguage();
    }

    private function getTargetLanguages()
    {
        return $this->variables->getTargetLanguages();
    }

    private function getDefaultLanguage()
    {
        return $this->variables->getDefaultLanguage();
    }

    private function getCurrentLanguage()
    {
        return $this->variables->getLanguageCode();
    }

    private function getExclusions()
    {
        $exclusions = $this->variables->getExclusions();

        return array_map(function ($value) {
            return [$value['rule'], $value['page_url']];
        }, $exclusions);
    }

    private function getLanguages()
    {
        $languages = $this->variables->getLanguages();

        $preparedLanguages = array_map(function ($value) {
            return [
                'title_en' => $value['title_en'],
                'title_native' => $value['title'],
                'code2' => $value['code2'],
                'code3' => $value['code3'],
            ];
        }, $languages);

        return array_values($preparedLanguages);
    }

    private function getBaseUrl()
    {
        return $this->variables->getSiteUrl();
    }

    private function getWpPatterns()
    {
        return  $this->variables->getWpPatterns();
    }
}