<?php
namespace Reliable;


interface IQueue
{
    public function enqueue();

    public function dequeue();

    public function sweep();

    public function release();

    public function renqueue();

}