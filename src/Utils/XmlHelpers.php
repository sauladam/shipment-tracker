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
     * @param string $termTag
     * @param string $descriptionTag
     *
     * @return null|string
     */
    protected function getDescriptionForTerm(
        $term,
        DOMXPath $xpath,
        $withLineBreaks = false,
        $termTag = 'dt',
        $descriptionTag = 'dd'
    ) {
        $terms = is_array($term) ? $term : [$term];

        $listNodes = $xpath->query("//dl");

        if (!$listNodes) {
            return null;
        }

        $descriptionPairBelongsToTerm = false;

        foreach ($listNodes as $list) {
            foreach ($list->childNodes as $descriptionNode) {
                if (get_class($descriptionNode) != DOMElement::class) {
                    continue;
                }

                if ($descriptionNode->tagName == $descriptionTag && $descriptionPairBelongsToTerm) {
                    return $this->getNodeValue($descriptionNode, $withLineBreaks);
                }

                if ($descriptionNode->tagName != $termTag) {
                    continue;
                }

                $descriptionTerm = $this->getNodeValue($descriptionNode);

                foreach ($terms as $term) {
                    if ($this->startsWith($term, $descriptionTerm)) {
                        $descriptionPairBelongsToTerm = true;
                        break;
                    }
                }
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
