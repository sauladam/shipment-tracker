<?php

namespace Sauladam\ShipmentTracker\Utils;

use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

trait XmlHelpers
{
    /**
     * Get the node value.
     *
     * @param DOMText|DOMNode $element
     * @param bool $preserveLineBreaks
     *
     * @return string
     */
    protected function getNodeValue($element = null, $preserveLineBreaks = false)
    {
        if (!$element) {
            return null;
        }

        $value = $preserveLineBreaks
            ? $this->getNodeValueWithLineBreaks($element)
            : $element->nodeValue;

        $value = trim($value);

        return preg_replace('/\s\s+/', ' ', $value);
    }


    /**
     * Get the node value but mark line breaks with a '|' (pipe).
     *
     * @param DOMText|DOMNode $element
     * @return string
     */
    protected function getNodeValueWithLineBreaks($element)
    {
        if (!$element->hasChildNodes()) {
            return $element->nodeValue;
        }

        $value = '';

        foreach ($element->childNodes as $node) {
            if ($node->nodeType != XML_ELEMENT_NODE || $node->nodeName != 'br') {
                $value .= $this->getNodeValue($node);
                continue;
            }

            $value .= "|";
        }

        return rtrim($value, '|');
    }


    /**
     * Get the description for the given terms.
     *
     * @param $term
     * @param DOMXPath $xpath
     * @param bool $withLineBreaks
     *
     * @return null|string
     */
    protected function getDescriptionForTerm($term, DOMXPath $xpath, $withLineBreaks = false)
    {
        $terms = is_array($term) ? $term : [$term];

        $listNodes = $xpath->query("//dl");

        if (!$listNodes) {
            return null;
        }

        foreach ($listNodes as $list) {
            $descriptionTerm = $this->getNodeValue($list->getElementsByTagName('dt')->item(0));

            if (!$descriptionTerm) {
                continue;
            }

            foreach ($terms as $term) {
                if (!$this->startsWith($term, $descriptionTerm)) {
                    continue;
                }

                $descriptions = $list->getElementsByTagName('dd');

                return $descriptions->length > 0
                    ? $this->getNodeValue($descriptions->item(0), $withLineBreaks)
                    : $this->getFallbackDescription($list, $term, $withLineBreaks);
            }
        }

        return null;
    }


    /**
     * If there was no dd-element in a description list (dl), check if there is a dt-element that does
     * not start with the description term. This mainly concerns UPS' markup, where the dd-elements
     * are missing and the descriptions are declared as dt-elements.
     *
     * @param DOMElement $list
     * @param $term
     * @param $withLineBreaks
     *
     * @return null|string
     */
    protected function getFallbackDescription(DOMElement $list, $term, $withLineBreaks)
    {
        foreach ($list->getElementsByTagName('dt') as $possibleDescriptionNode) {
            $possibleDescription = $this->getNodeValue($possibleDescriptionNode, $withLineBreaks);

            if ($possibleDescription && !$this->startsWith($term, $possibleDescription)) {
                return $possibleDescription;
            }
        }

        return null;
    }


    /**
     * Check if the subject starts with the given string.
     *
     * @param string $start
     * @param string $subject
     * @return bool
     */
    protected function startsWith($start, $subject)
    {
        return strpos($subject, $start) === 0;
    }
}
