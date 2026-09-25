<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * The TreeHelper class provides methods to display tree-like structures.
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @implements \RecursiveIterator<int, TreeNode>
 */
final class TreeHelper implements \RecursiveIterator
{
    /**
     * @var \Iterator<int, TreeNode>
     */
    private \Iterator $children;

    private ?TreeNode $preparedNode = null;
    private ?\Iterator $preparedChildren = null;
    private ?bool $preparedHasChildren = null;

    private function __construct(
        private readonly OutputInterface $output,
        private readonly TreeNode $node,
        private readonly TreeStyle $style,
        ?\Iterator $children = null,
    ) {
        $this->children = $children ?? new \IteratorIterator($this->node->getChildren());
        if (null === $children) {
            $this->children->rewind();
        }
    }

    public static function createTree(OutputInterface $output, string|TreeNode|null $root = null, iterable $values = [], ?TreeStyle $style = null): self
    {
        $node = $root instanceof TreeNode ? $root : new TreeNode($root ?? '');

        return new self($output, TreeNode::fromValues($values, $node), $style ?? TreeStyle::default());
    }

    public function current(): TreeNode
    {
        return $this->children->current();
    }

    public function key(): int
    {
        return $this->children->key();
    }

    public function next(): void
    {
        $this->resetPreparedChildren();
        $this->children->next();
    }

    public function rewind(): void
    {
        $this->resetPreparedChildren();
        $this->children->rewind();
    }

    public function valid(): bool
    {
        return $this->children->valid();
    }

    public function hasChildren(): bool
    {
        $current = $this->current();

        if ($this->preparedNode === $current) {
            return (bool) $this->preparedHasChildren;
        }

        $this->preparedNode = $current;
        $this->preparedChildren = new \IteratorIterator($current->getChildren());
        $this->preparedChildren->rewind();
        $this->preparedHasChildren = $this->preparedChildren->valid();

        return $this->preparedHasChildren;
    }

    public function getChildren(): \RecursiveIterator
    {
        $current = $this->current();
        $children = $this->preparedNode === $current ? $this->preparedChildren : null;

        $this->resetPreparedChildren();

        return new self($this->output, $current, $this->style, $children);
    }

    private function resetPreparedChildren(): void
    {
        $this->preparedNode = null;
        $this->preparedChildren = null;
        $this->preparedHasChildren = null;
    }

    /**
     * Recursively renders the tree to the output, applying the tree style.
     */
    public function render(): void
    {
        $treeIterator = new \RecursiveTreeIterator($this);

        $this->style->applyPrefixes($treeIterator);

        $this->output->writeln($this->node->getValue());

        // Keep the nodes on the current path instead of all nodes seen so far. The
        // same TreeNode can legitimately be attached to more than one branch; that
        // is not a cycle and must not prevent the second branch from being rendered.
        $path = [$this->node];
        foreach ($treeIterator as $node) {
            $currentNode = $node instanceof TreeNode ? $node : $treeIterator->getInnerIterator()->current();
            $depth = $treeIterator->getDepth() + 1; // The root is not part of RecursiveTreeIterator.
            $path = \array_slice($path, 0, $depth);

            foreach ($path as $ancestor) {
                if ($ancestor === $currentNode) {
                    throw new \LogicException(\sprintf('Cycle detected at node: "%s".', $currentNode->getValue()));
                }
            }
            $path[$depth] = $currentNode;

            $this->output->writeln($node);
        }
    }
}
