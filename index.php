<?php
// Random landing page selector
// 50% chance for index.html, 50% chance for index_v2.html

$randomChoice = rand(0, 1);

if ($randomChoice === 0) {
    include 'index.html';
} else {
    include 'index_v2.html';
}
?>
