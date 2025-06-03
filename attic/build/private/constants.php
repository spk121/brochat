<?php
/**
 * Global Constants for Security and Authentication
 *
 * This file defines application-wide constants related to rate limiting, 
 * lockout durations, CSRF protections, and invitation codes. These values
 * help enforce security policies across the system.
 *
 * @package SecurityUtilities
 * @author  Your Name
 * @version 1.0
 * @license MIT License
 */

/**
 * Maximum allowed login attempts before account lockout.
 * 
 * @var int
 */
define('RATE_LIMIT_ATTEMPTS', 6);

/**
 * Lockout duration in seconds (15 minutes) after exceeding login attempts.
 * 
 * @var int
 */
define('LOCKOUT_DURATION', 15 * 60);

/**
 * CSRF token timeout in seconds (1 week).
 * 
 * @var int
 */
define('CSRF_TOKEN_TIMEOUT', 604800);

/**
 * Invite code expiration in seconds (1 week).
 * 
 * @var int
 */
define('INVITE_CODE_EXPIRATION', 604800);

/**
 * Default max uses for invitation codes before expiry.
 * 
 * @var int
 */
define('INVITE_CODE_MAX_USES', 5);

/**
 * Default max inactivity time before forced logout.
 * 
 * @var int
 */
define('SESSION_INACTIVITY_TIMEOUT', 604800); // 1 week
?>
