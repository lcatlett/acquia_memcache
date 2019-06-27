<?php

namespace Drupal\acquia_memcache;

use Drupal\Core\Site\Settings;
use Memcached;
use Psr\Log\LoggerInterface;

/**
 * Class MemcacheStorage.
 *
 * @package Drupal\acquia_memcache
 */
class MemcacheStorage {

  /**
   * The Memcached instance.
   *
   * @var \Memcached
   */
  protected $memcached;

  /**
   * Environment-specific Memcache settings.
   *
   * @var array|null
   */
  protected $memcacheSettings;

  /**
   * Logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Boolean of current instances ability to interact with Memcache servers.
   *
   * @var bool
   */
  protected $isConnected = TRUE;

  /**
   * MemcacheStorage constructor.
   *
   * @param \Psr\Log\LoggerInterface $memcache_logger
   *   The Memcache logger channel instance.
   */
  public function __construct(LoggerInterface $memcache_logger) {
    $this->logger = $memcache_logger;
    $this->memcacheSettings = Settings::get('memcache');
    // Instantiate the Memcache connection.
    $this->initialize();
  }

  /**
   * Initializes the MemcacheStorage instance.
   *
   * This will interface with configured Memcache servers.
   */
  private function initialize() {
    // Check that Memcached PECL is installed/configured.
    if (!class_exists('Memcached')) {
      $this->logger->notice("Memcached PHP extension library is not installed/configured on this server.");
      $this->isConnected = FALSE;
      return;
    }
    $this->memcached = new Memcached();

    // Optimize Memcached settings for more fluent fail-over.
    // See http://php.net/manual/en/memcached.addservers.php.
    $this->memcached->setOption(Memcached::OPT_CONNECT_TIMEOUT, 100);
    $this->memcached->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
    $this->memcached->setOption(Memcached::OPT_REMOVE_FAILED_SERVERS, TRUE);

    // Retrieve configured servers.
    $configured_servers = $this->getConfiguredServers();

    // Only attempt to add servers when they're configured in environment
    // and are not already added to the daemon.
    if (!empty($configured_servers) && empty($this->memcached->getServerList())) {
      $servers = [];
      foreach ($configured_servers as $configured_server => $status) {
        $servers[] = explode(':', $configured_server);
      }

      $success = $this->memcached->addServers($servers);

      if (!$success) {
        $this->logger->error("Error initializing Memcache connection - Failed adding
         servers to Memcache daemon. \nMemcache Result Message: {$this->memcached->getResultMessage()}");
        $this->isConnected = FALSE;
      }
    }
  }

  /**
   * Gets a value stored in Memcache by a given key.
   *
   * This method will return NULL when either the Memcache instance
   * is not ready to communicate with the Memcache servers or the given
   * cache key did not map to a stored value.
   *
   * @param string $key
   *   The cache key.
   *
   * @return mixed|null
   *   The value stored in cache, or NULL if unable to perform a get.
   */
  public function get($key) {
    if ($this->isConnected()) {
      $value = $this->memcached->get($key);
      if ($value !== FALSE) {
        return $value;
      }
    }

    return NULL;
  }

  /**
   * Stores a value in Memcache under a given key.
   *
   * This method returns FALSE when either the Memcache instance is not
   * connected or when the set attempt using the given key fails.
   *
   * @param string $key
   *   The cache key under which to store the value.
   * @param mixed $value
   *   The value that will be stored.
   * @param int $expiration
   *   The expiration time.
   *
   * @return bool
   *   Whether or not the key/value was set in Memcache.
   */
  public function set($key, $value, $expiration = 0) {
    $success = FALSE;
    if ($this->isConnected()) {
      $success = $this->memcached->set($key, $value, $expiration);
      if (!$success) {
        $this->logger->warning("Unable to set value using key: {$key}.
          \nMemcache Result Message: {$this->memcached->getResultMessage()}");
      }
    }

    return $success;
  }

  /**
   * Deletes a key/value pair from Memcache.
   *
   * This method returns FALSE when either the Memcache instance is not
   * connected or when the delete attempt for the given key fails.
   *
   * @param string $key
   *   The cache key.
   *
   * @return bool
   *   Whether or not the key/value was deleted from Memcache.
   */
  public function delete($key) {
    $success = FALSE;
    if ($this->isConnected()) {
      $success = $this->memcached->delete($key);
      if (!$success) {
        $this->logger->warning("Unable to delete key/value using key: {$key}.
          \nMemcache Result Message: {$this->memcached->getResultMessage()}");
      }
    }

    return $success;
  }

  /**
   * Returns whether the Memcache instance is ready to perform cache operations.
   *
   * @return bool
   *   Whether the Memcache storage instance is connected.
   */
  public function isConnected() {
    return $this->isConnected;
  }

  /**
   * Returns the Memcache key prefix set in Settings.
   *
   * @return string
   *   The cache key prefix.
   */
  public function getKeyPrefix() {
    $prefix = '';
    if (!empty($this->memcacheSettings) && isset($this->memcacheSettings['key_prefix'])) {
      $prefix = $this->memcacheSettings['key_prefix'];
    }
    else {
      $this->isConnected = FALSE;
    }

    return $prefix;
  }

  /**
   * Returns list of configured Memcache servers from Settings.
   *
   * @return array
   *   The configured Memcache servers.
   */
  protected function getConfiguredServers() {
    $servers = [];
    if (!empty($this->memcacheSettings) && isset($this->memcacheSettings['servers'])) {
      $servers = $this->memcacheSettings['servers'];
    }
    else {
      $this->isConnected = FALSE;
    }

    return $servers;
  }

}
