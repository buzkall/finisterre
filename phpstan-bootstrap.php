<?php

// Larastan boots a bare Testbench app, so register the package view namespace
// to let it validate "finisterre::" literals as view-strings.
app('view')->addNamespace('finisterre', __DIR__ . '/resources/views');
