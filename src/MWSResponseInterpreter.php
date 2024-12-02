<?php
declare(strict_types=1);

namespace LaurensLyceum\MWS\Client;

use LaurensLyceum\MWS\Client\Exceptions\MWSResponseInterpretationException;
use SimpleXMLElement;

/**
 * @see MWSResponseInterpreter::interpretResponse()
 * @see MWSClient::call()
 */
class MWSResponseInterpreter
{

    /**
     * Interpret a response from MWS.
     *
     * @param SimpleXMLElement $xml
     * @return array The parsed response table. See {@link parseResponseTable()} for the mapping methodology.
     *
     * @throws MWSResponseInterpretationException
     *
     * @see MWSClient::call()
     * @see parseResponseTable()
     */
    public static function interpretResponse(SimpleXMLElement $xml): array
    {
        if (isset($xml->Exception)) {
            $message = isset($xml->ExceptionMsg) ? (string)$xml->ExceptionMsg : "No ExceptionMsg";
            throw new MWSResponseInterpretationException("MWS returned exception: $xml->Exception - $message", $xml);
        }

        if (!isset($xml->Result)) {
            throw new MWSResponseInterpretationException("Missing Result node", $xml);
        }

        if ((string)$xml->Result !== "True") {
            try {
                $errorTable = self::parseResponseTable($xml);
            } catch (MWSResponseInterpretationException $e) {
                throw new MWSResponseInterpretationException("MWS returned non-true result ($xml->Result) and could not parse response table with errors", $xml, $e);
            }

            $errorSummaries = array_map(
                fn($error) => ($error->Fout_omschrijving ?? "No summary") . " (" . ($error->Fout_nummer ?? "no code") . ")",
                $errorTable
            );
            throw new MWSResponseInterpretationException("MWS returned non-true result ($xml->Result) with the following errors: " . implode(", ", $errorSummaries), $xml);
        }

        // CHECK ResultMessage?

        // Looking good!
        return self::parseResponseTable($xml);
    }

    /**
     * Parse MWS response table of the following format:
     *
     * <code>
     *  <Response>¹
     *      <Table>
     *          <Plural>²
     *              <Singular>³
     *                  <ColumnA>...</ColumnA>⁴
     *                  <ColumnB>...</ColumnB>⁴
     *              </Singular>
     *              <Singular>³
     *                  ...
     *              </Singular>
     *              ...
     *          </Plural>
     *      </Table>
     *  </Response>
     * </code>
     *
     * 1. `$xml` parameter
     * 2. Could be anything, as long as there's exactly one.
     * 3. Could be anything, as long as they're all of the same type.
     * 4. Mapped to keys of the returned array like this:
     *
     * <code>
     *  [
     *      [
     *          ColumnA => ...,
     *          ColumnB => ...,
     *      ],
     *      ...
     *  ]
     * </code>
     *
     * @param SimpleXMLElement $xml
     * @return array
     *
     * @throws MWSResponseInterpretationException
     *
     * @see interpretResponse()
     */
    private static function parseResponseTable(SimpleXMLElement $xml): array
    {
        // TODO Check for extraneous nodes

        $table = $xml->Table;
        if (!isset($table)) {
            throw new MWSResponseInterpretationException("Missing Table node", $xml);
        }

        // Expect exactly one 'plural' direct child node
        $directChildren = $table->children();
        if ($directChildren->count() !== 1) {
            throw new MWSResponseInterpretationException("Incorrect amount of child nodes for response table, expected 1 got {$directChildren->count()}", $table);
        }
        $plural = $directChildren[0];

        // Expect 'plural' to have children of a constant 'singular' type
        $singularNodesName = null;
        $parsedTable = [];

        foreach ($plural->children() as $row) {
            $singularNodesName ??= $row->getName();
            if ($singularNodesName !== $row->getName()) {
                throw new MWSResponseInterpretationException("Unexpected child node of {$plural->getName()} in response table, expected '$singularNodesName' got '{$row->getName()}'", $table);
            }

            // TODO Parse directly into class instance
            $parsedRow = [];
            foreach ($row->children() as $column) {
                $parsedRow[$column->getName()] = (string)$column;
            }
            $parsedTable[] = $parsedRow;
        }

        return $parsedTable;
    }

}
