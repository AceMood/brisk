<?php

/**
 * Identity function, returns its argument unmodified.
 *
 * This is useful almost exclusively as a workaround to an oddity in the PHP
 * grammar -- this is a syntax error:
 *
 *    COUNTEREXAMPLE
 *    new Thing()->doStuff();
 *
 * ...but this works fine:
 *
 *    id(new Thing())->doStuff();
 *
 * @param   wild Anything.
 * @return  wild Unmodified argument.
 */
function id($x) {
    return $x;
}

/**
 * Access an array index, retrieving the value stored there if it exists or
 * a default if it does not. This function allows you to concisely access an
 * index which may or may not exist without raising a warning.
 *
 * @param  array  $array Array to access.
 * @param  string $key   Index to access in the array.
 * @param  mixed  $default  Default value to return if the key is not present in the
 *                 array.
 * @return wild    If `$array[$key]` exists, that value is returned. If not,
 *                 $default is returned without raising a warning.
 */
function idx(array $array, $key, $default = null) {
    // isset() is a micro-optimization - it is fast but fails for null values.
    if (isset($array[$key])) {
        return $array[$key];
    }

    // Comparing $default is also a micro-optimization.
    if ($default === null || array_key_exists($key, $array)) {
        return null;
    }

    return $default;
}

/**
 * Similar to @{function:coalesce}, but less strict: returns the first
 * non-`empty()` argument, instead of the first argument that is strictly
 * non-`null`. If no argument is nonempty, it returns the last argument. This
 * is useful idiomatically for setting defaults:
 *
 *   $display_name = nonempty($user_name, $full_name, "Anonymous");
 *
 * @param  ...         Zero or more arguments of any type.
 * @return mixed       First non-`empty()` arg, or last arg if no such arg
 *                     exists, or null if you passed in zero args.
 */
function nonempty(/* ... */) {
    $args = func_get_args();
    $result = null;
    foreach ($args as $arg) {
        $result = $arg;
        if ($arg) {
            break;
        }
    }
    return $result;
}

/**
 * Returns the first element of an array. Exactly like reset(), but doesn't
 * choke if you pass it some non-referenceable value like the return value of
 * a function.
 *
 * @param    array Array to retrieve the first element from.
 * @return   wild  The first value of the array.
 */
function head(array $arr) {
    return reset($arr);
}

/**
 * Returns the last element of an array. This is exactly like `end()` except
 * that it won't warn you if you pass some non-referencable array to
 * it -- e.g., the result of some other array operation.
 *
 * @param    array $arr Array to retrieve the last element from.
 * @return   wild  The last value of the array.
 */
function last(array $arr) {
    return end($arr);
}

/**
 * Returns the first key of an array.
 *
 * @param    array       Array to retrieve the first key from.
 * @return   int|string  The first key of the array.
 */
function head_key(array $arr) {
    reset($arr);
    return key($arr);
}

/**
 * Returns the last key of an array.
 *
 * @param    array       Array to retrieve the last key from.
 * @return   int|string  The last key of the array.
 */
function last_key(array $arr) {
    end($arr);
    return key($arr);
}

/**
 * Split a corpus of text into lines. This function splits on "\n", "\r\n", or
 * a mixture of any of them.
 *
 * NOTE: This function does not treat "\r" on its own as a newline because none
 * of SVN, Git or Mercurial do on any OS.
 *
 * @param string Block of text to be split into lines.
 * @param bool If true, retain line endings in result strings.
 * @return list List of lines.
 */
function phutil_split_lines($corpus, $retain_endings = true) {
    if (!strlen($corpus)) {
        return array('');
    }

    // Split on "\r\n" or "\n".
    if ($retain_endings) {
        $lines = preg_split('/(?<=\n)/', $corpus);
    } else {
        $lines = preg_split('/\r?\n/', $corpus);
    }

    // If the text ends with "\n" or similar, we'll end up with an empty string
    // at the end; discard it.
    if (end($lines) == '') {
        array_pop($lines);
    }

    if ($corpus instanceof PhutilSafeHTML) {
        return array_map('phutil_safe_html', $lines);
    }

    return $lines;
}

function phutil_is_windows() {
    // We can also use PHP_OS, but that's kind of sketchy because it returns
    // "WINNT" for Windows 7 and "Darwin" for Mac OS X. Practically, testing for
    // DIRECTORY_SEPARATOR is more straightforward.
    return (DIRECTORY_SEPARATOR != '/');
}

/**
 * Assert that passed data can be converted to string.
 *
 * @param  string    Assert that this data is valid.
 * @return void
 *
 * @task   assert
 */
function assert_stringlike($parameter) {
    switch (gettype($parameter)) {
        case 'string':
        case 'NULL':
        case 'boolean':
        case 'double':
        case 'integer':
            return;
        case 'object':
            if (method_exists($parameter, '__toString')) {
                return;
            }
            break;
        case 'array':
        case 'resource':
        case 'unknown type':
        default:
            break;
    }
    throw new InvalidArgumentException(
        pht(
            'Argument must be scalar or object which implements %s!',
            '__toString()'));
}