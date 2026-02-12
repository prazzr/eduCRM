<?php
// tools/audit_global_classes.php
// Scans for usage of global classes inside namespaced files without leading backslash

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$regex = '/new\s+(\PDO|Exception|PDOException|DateTime|DateInterval|stdClass|SimpleXMLElement)\b|(?<!\\\\)(\PDO|Exception|PDOException|DateTime|DateInterval|stdClass|SimpleXMLElement)::|Type\s+(\PDO|Exception)\b|\s(PDO|Exception)\s+\$/';

// Refined regex to find "PDO" but NOT "\PDO" and NOT "use PDO"
// We are looking for tokens basically.

echo "Scanning for un-namespaced global classes...\n";

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php')
        continue;
    if (strpos($file->getPathname(), 'vendor') !== false)
        continue;
    if (strpos($file->getPathname(), 'tools') !== false)
        continue;

    $content = file_get_contents($file->getPathname());

    // Check if file is namespaced
    if (!preg_match('/namespace\s+EduCRM/', $content)) {
        continue;
    }

    $tokens = token_get_all($content);
    $namespaceDeclared = false;

    foreach ($tokens as $index => $token) {
        if (!is_array($token))
            continue;

        $id = $token[0];
        $text = $token[1];
        $line = $token[2];

        // Global classes list
        $globals = ['PDO', 'Exception', 'PDOException', 'DateTime', 'DateInterval', 'stdClass', 'InvalidArgumentException', 'RuntimeException'];

        if ($id === T_STRING && in_array($text, $globals)) {
            // Check previous significant token
            $prevIndex = $index - 1;
            while ($prevIndex >= 0 && is_array($tokens[$prevIndex]) && ($tokens[$prevIndex][0] === T_WHITESPACE || $tokens[$prevIndex][0] === T_COMMENT)) {
                $prevIndex--;
            }

            $prevToken = $tokens[$prevIndex] ?? null;
            $prevText = is_array($prevToken) ? $prevToken[1] : $prevToken;

            // Ignore if 'use PDO;', 'namespace ... PDO', 'class PDO', '\PDO'
            if ($prevText === '\\')
                continue; // Already namespaced \PDO
            if (is_array($prevToken) && $prevToken[0] === T_USE)
                continue; // use PDO;
            if (is_array($prevToken) && $prevToken[0] === T_NAMESPACE)
                continue;
            if (is_array($prevToken) && $prevToken[0] === T_CLASS)
                continue;
            if (is_array($prevToken) && $prevToken[0] === T_FUNCTION)
                continue; // function PDO() ??

            echo "Potential Issue: $text at " . $file->getPathname() . ":$line\n";
        }
    }
}
