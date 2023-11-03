<?php declare(strict_types=1);

namespace simplerest\core\libs;

/*
    https://stackoverflow.com/a/2932200/980631
*/
class SortedIterator extends \SplHeap
{
    public function __construct(\Iterator $iterator)
    {
        foreach ($iterator as $item) {
            $this->insert($item);
        }
    }

    public function compare($b,$a)
    {
        return strcmp($a->getRealpath(), $b->getRealpath());
    }
}

