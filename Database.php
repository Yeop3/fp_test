<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        try {
            if (count($args) === 0) {
                return $query;
            }

            [$query, $args] = $this->transformQuery($query, $args);

            preg_match_all('/[?]+[#dfa]|[?]/', $query, $types);

            if (count($types[0]) === 0) {
                return $query;
            }

            foreach ($types[0] as $typeKey => $type) {
                $query = match ($type) {
                    '?' => $this->replaceRegular($query, $args[$typeKey]),
                    '?#' => $this->replaceIdOrIds($query, $args[$typeKey]),
                    '?d' => $this->replaceInt($query, $args[$typeKey]),
                    '?a' => $this->replaceArray($query, $args[$typeKey]),
                    '?f' => $this->replaceFloat($query, $args[$typeKey]),
                    default => throw new \Exception('Unexpected match value')
                };
            }

            return $query;
        } catch (\Throwable $throwable) {
            throw new Exception($throwable->getMessage());
        }
    }

    public function skip()
    {
        return 'skip';
    }

    protected function replaceRegular(string $query, $arg): string
    {
        $value = $this->transformType($arg);
        return preg_replace('/(?<=\s)\?(?=\s|$)/', $value, $query, 1);
    }

    protected function replaceInt(string $query, int|null $arg): string
    {
        if (is_null($arg)) {
            return preg_replace('/\?d/', "NULL", $query, 1);
        }

        return preg_replace('/\?d/', $arg, $query, 1);
    }

    protected function replaceFloat(string $query, float|null $arg): string
    {
        if (is_null($arg)) {
            return preg_replace('/\?f/', "NULL", $query, 1);
        }

        return preg_replace('/\?f/', $arg, $query, 1);
    }

    protected function replaceArray(string $query, array $arg): string
    {
        $string = '';
        $lastKey = array_key_last($arg);
        foreach ($arg as $key => $value) {
            if (gettype($key) === 'string') {
                $value = $this->transformType($value);
                $string .= "`$key` = $value";
            } else {
                $string .= "$value";
            }

            if ($key !== $lastKey) {
                $string .= ", ";
            }
        }

        return preg_replace('/\?a/', $string, $query, 1);
    }

    protected function replaceIdOrIds(string $query, $arg): string
    {
        $string = '';
        if (is_array($arg)) {
            $lastKey = array_key_last($arg);
            foreach ($arg as $key => $value) {
//                $value = $this->transformType($value);
                $string .= "`$value`";
                if ($key !== $lastKey) {
                    $string .= ", ";
                }
            }
        } else {
            $string = "`$arg`";
        }
        return preg_replace('/\?#/', $string, $query, 1);
    }

    protected function transformType($value)
    {
        return match (gettype($value)) {
            "integer", "boolean" => (int)$value,
            "double" => (float)$value,
            "string" => "'$value'",
            "NULL" => "NULL",
            default => throw new Exception()
        };
    }

    protected function transformQuery(string $query, array $args): array
    {
        preg_match_all('/[?]+[#dfa]|[?]|\{.*?\}/', $query, $elements);

        foreach ($elements[0] as $key => $element) {
            if (str_contains($element, '{')) {
                $str = '';
                if ($args[$key] !== 'skip') {
                    $str = str_replace(['{', '}'], ['', ''], $element);
                } else {
                    unset($args[$key]);
                }
                $query = str_replace($element, $str, $query);
            };
        }

        $args = array_values($args);

        return [$query, $args];
    }
}
