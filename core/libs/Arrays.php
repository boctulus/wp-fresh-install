<?php declare(strict_types=1);

namespace simplerest\core\libs;

class Arrays 
{
    /*
        Trata un array no-asociativo como un Set (conjunto)

        y agrega el valor sino existe en el mismo
    */
    static function pushIfNotExists(array &$array, $value, bool $strict = false){
        $id = array_search($value, $array, $strict);

        if ($id !== false){
            $array[] = $id;
        }
    }

    /*
        Trata un array no-asociativo como un Set (conjunto)

        Si existe el valor en un array no-asociativo,
        lo encuentra y destruye
    */
    static function destroyIfExists(array &$array, $value, bool $strict = false){
        $id = array_search($value, $array, $strict);

        if ($id !== false){
            unset($array[$id]);
        }
    }

    static function getColumns(array $rows, array $keys) {
        $filtered = array();

        foreach ($rows as $row) {
            $filteredRow = [];

            foreach ($keys as $key) {
                if (array_key_exists($key, $row)) {
                    $filteredRow[$key] = $row[$key];
                }
            }

            $filtered[] = $filteredRow;
        }

        return $filtered;
    }
    
    /*
        Trim every element of array
    */
    static function trimArray(array $arr){
        return array_map('trim', $arr);
    }

    static function rtrimArray(array $arr){
        return array_map('rtrim', $arr);
    }

    static function ltrimArray(array $arr){
        return array_map('rtrim', $arr);
    }
       
    /**
     * Gets the first key of an array
     *
     * @param array $array
     * @return mixed
     */
    static function arrayKeyFirst(array $arr) {
        foreach($arr as $key => $unused) {
            return $key;
        }
        return null;
    }

    static function arrayValueFirst(array $arr) {
        foreach($arr as $val) {
            return $val;
        }
        return null;
    }

    /**
     * shift
     *
     * @param  array  $arr
     * @param  string $key
     * @param  string $default_value
     *
     * @return mixed
     */
    static function shift(&$arr, $key, $default_value = NULL)
    {
        $out = $arr[$key] ?? $default_value;
        unset($arr[$key]);
        return $out;
    }

    static function shiftOrFail(&$arr, $key, string $error_msg)
    {
        if (!isset($arr[$key])){
            throw new \Exception(sprintf($error_msg, [$key]));
        }

        $out = $arr[$key];
        unset($arr[$key]);
        return $out;
    }

    /**
     * nonassoc
     * Turns associative into non associative array
     * 
     * @param  array $arr
     *
     * @return array
     */
    static function nonassoc(array $arr){
        $out = [];
        foreach ($arr as $key => $val) {
            $out[] = [$key, $val];
        }
        return $out;
    }
 
    /*
        Solo se es no-asociativo si ninguna key es no-numerica
    */
    static function is_non_assoc(array $arr)
    {
        $keys = array_keys($arr);

        foreach($keys as $key){
            if (!is_int($key)){
                return false;
            }
        }		

        return true;
    }

    /*
        Un array es asociativo con que al menos una key sea un string
    */
    static function is_assoc(array $arr){
        return !static::is_non_assoc($arr);
    }

    /**
     * A str_replace_array for PHP
     *
     * As described in http://php.net/str_replace this wouldnot make sense
     * However there are chances that we need it, so often !
     * See https://wiki.php.net/rfc/cyclic-replace
     *
     * @author Jitendra Adhikari | adhocore <jiten.adhikary@gmail.com>
     *
     * @param string $search  The search string
     * @param array  $replace The array to replace $search in cyclic order
     * @param string $subject The subject on which to search and replace
     *
     * @return string
     */
    static function str_replace_array($search, array $replace, $subject)
    {
        if (empty($subject)){
            return '';
        }

        if (0 === $tokenc = substr_count($subject, $search)) {
            return $subject;
        }
        $string  = '';
        if (count($replace) >= $tokenc) {
            $replace = array_slice($replace, 0, $tokenc);
            $tokenc += 1; 
        } else {
            $tokenc = count($replace) + 1;
        }
        foreach(explode($search, $subject, $tokenc) as $part) {
            $string .= $part.array_shift($replace);
        }
        return $string;
    }

    static function shuffleAssoc($my_array)
	{
        $keys = array_keys($my_array);

        shuffle($keys);

        $new = [];
        foreach($keys as $key) {
            $new[$key] = $my_array[$key];
        }

        return $new;
    }

    /*
        https://stackoverflow.com/a/145348/980631
    */
    static function isMultidim($a) {
        foreach ($a as $v) {
            if (is_array($v)) return true;
        }
        return false;
    }

    /*
         $a = [
            ['a', 'c'],
            ['x' => 7], // <--- false
            ['a', 'c', 5],
        ];
        
        dd(
            Arrays::areSimpleAllSubArrays($a)
        );
    */
    static function areSimpleAllSubArrays($a, $throw_exception = false){
        foreach ($a as $k => $sub){
            if (!is_int($k)){
                return false;
            }

            if (!is_array($sub)){
                if ($throw_exception){
                    throw new \InvalidArgumentException("An element -'$sub'- is not a sub-array as expected");
                }

                return false;
            }

            if (static::is_assoc($sub)){
                return false;
            }
        }

        return true;
    }

    /*
        Dados dos arrays (no-asociativos) $a y $b, se altera el orden en que aparecen los elementos del primer array ($a)
        de forma que sigan el orden impuesto por el array segundo ($b)

        $a sigue el orden impuesto por $a

        Estrictamete hay varias soluciones posibles, todas validas.
    */
    static function followOrder(array $a, array $b) {
        if (count($b) == 0){
            return;
        }

        $first_pos_b_in_a = array_search($b[0], $a);

        list($a1, $a2) = array_chunk($a, $first_pos_b_in_a);

        $a1 = array_diff($a1, $b);
        $a2 = array_diff($a2, $b);

        $a = array_merge($a1, $b, $a2);
        
        return $a;
    }

    // seria mejor en ciertos casos con generadores
    static function chunk($data, $length = null, $offset = 0) {
        if ($offset > 0) {
            $data = array_slice($data, $offset);
        }

        if ($length === null) {
            return $data;
        }

        return array_slice($data, 0, $length);
    }

    /*
        Dado un contenido (entero, string, array,...) y un "path", deja el contenido dentro del path 
        en el array en construccion o sea anidado dentro las keys del path

        Ej:

        $path   = 'data.products';
        $result = Arrays::makeArray($array, $path);
    */
    static function makeArray($content, string $path) {
        $rows_path_s = explode('.', $path);
        $result      = [];
        $current     = &$result;
        
        foreach ($rows_path_s as $key) {
            $current[$key] = [];
            $current = &$current[$key];
        }
        
        $current = $content;
        
        return $result;
    }
}

