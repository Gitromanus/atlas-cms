<?php

namespace App\Services\CommerceML;

use SimpleXMLElement;
use XMLReader;

/**
 * Утилиты потокового чтения XML-файлов CommerceML.
 */
class XmlUtils
{
    /**
     * Перебирает элементы с заданным именем, передавая каждый как SimpleXMLElement.
     *
     * @param  callable(SimpleXMLElement): void  $callback
     */
    public static function each(string $path, string $elementName, callable $callback): void
    {
        $reader = new XMLReader;

        if (! $reader->open($path, 'UTF-8')) {
            throw new \RuntimeException("Не удалось открыть XML-файл: {$path}");
        }

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === $elementName) {
                $xml = simplexml_load_string($reader->readOuterXml());

                if ($xml !== false) {
                    $callback($xml);
                }
            }
        }

        $reader->close();
    }

    /**
     * Значение дочернего элемента или null.
     */
    public static function child(SimpleXMLElement $node, string $name): ?string
    {
        $child = $node->{$name};

        return isset($child) ? trim((string) $child) : null;
    }

    /**
     * Список Ид из контейнера (например, Группы → Ид).
     *
     * Поддерживаются обе структуры CommerceML:
     *  - <Группы><Ид>GUID</Ид></Группы> (товары УТ)
     *  - <Группы><Группа><Ид>GUID</Ид></Группа></Группы> (классификатор)
     *
     * @return array<int, string>
     */
    public static function ids(SimpleXMLElement $node, string $container): array
    {
        $ids = [];

        if (! isset($node->{$container})) {
            return $ids;
        }

        foreach ($node->{$container}->children() as $item) {
            // <Ид> лежит напрямую в контейнере: <Группы><Ид>GUID</Ид></Группы>
            if ($item->getName() === 'Ид') {
                $value = trim((string) $item);

                if ($value !== '') {
                    $ids[] = $value;
                }

                continue;
            }

            // <Ид> внутри дочернего элемента: <Группы><Группа><Ид>GUID</Ид></Группа></Группы>
            $id = self::child($item, 'Ид');

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Приводит значение «true»/«1»/«да» к boolean.
     */
    public static function bool(?string $value): bool
    {
        return in_array(strtolower((string) $value), ['true', '1', 'да', 'yes'], true);
    }
}