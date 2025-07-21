<?php

/**
 * Asi bych nechal zdědit třidu pro zpracování, která bude obecná, ale metody, které se v ní volají, budou upravitelné pro jednotlivé APIManagery.
 */
abstract class APIManager {
    abstract protected function responseRequest();
}