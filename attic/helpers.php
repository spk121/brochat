<?php
/**
 * CSRF Token Generator
 *
 * This script defines a function for generating a simple CSRF token.
 * The token consists of four randomly selected uppercase letters.
 *
 * @package SecurityUtilities
 * @author  Your Name
 * @version 1.0
 * @license MIT License
 */

/**
 * Generates a simple CSRF token using four random uppercase letters.
 *
 * This function provides basic protection against CSRF attacks by
 * generating a unique token that can be associated with a user session.
 *
 * @return string A randomly generated CSRF token (4 uppercase letters)
 */
function generateCsrfToken() {
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'; // Set of allowed characters
    $token = ''; // Initialize the token string
    
    // Loop to generate a 4-character token
    for ($i = 0; $i < 4; $i++) {
        $token .= $letters[random_int(0, 25)]; // Select a random letter
    }
    
    return $token; // Return the generated token
}
?>
