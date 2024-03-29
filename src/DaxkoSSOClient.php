<?php

namespace Drupal\daxko_sso;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Client;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class DaxkoSSOClient
 *
 * @package Drupal\daxko_sso
 */
class DaxkoSSOClient {

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $http;

  /**
   * Daxko API credentials config.
   */
  protected $daxkoConfig;

  /**
   * Logger for daxko_sso.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Daxko client contstructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The EntityTypeManager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param CacheBackendInterface $cache
   *   Cache default.
   * @param \GuzzleHttp\Client $http
   *   The http client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Client $http, LoggerChannelFactoryInterface $loggerChannelFactory) {
    $this->daxkoConfig = $config_factory->get('daxko_sso.settings');
    $this->http = $http;
    $this->logger = $loggerChannelFactory->get('daxko_sso');
  }

  /**
   * Get first entrance token based on API credentials.
   */
  public function getDaxkoPartnerToken() {

    try {
      $response = $this->http->request(
        'POST',
        $this->daxkoConfig->get('base_uri') . 'partners/oauth2/token',
        [
          'form_params' => [
            'grant_type' => 'client_credentials',
            'client_id' => $this->daxkoConfig->get('user'),
            'client_secret' => $this->daxkoConfig->get('pass'),
            'scope' => 'client:' . $this->daxkoConfig->get('client_id'),
          ],
          'headers' => [
            'Authorization' => "Bearer " . $this->daxkoConfig->get('referesh_token'),
          ],
        ]);
      return json_decode((string) $response->getBody())->access_token;
    } catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }

  }

  /**
   * Send a request using the Daxko token.
   *
   * @param string $method
   *   The http method.
   * @param string $uri
   *   Daxko API endpoint after `/v3/` and with no leading slash.
   * @param array $options
   *   Options to send.
   *
   * @return mixed
   *   The successful response body or error.
   */
  public function request(string $method, string $uri, array $options = []) {
    // If no Auth is passed, set it.
    if (!isset($options['headers']['Authorization'])) {
      $token = $this->getDaxkoPartnerToken();
      $headers = [
        'headers' => [
          'Authorization' => "Bearer " . $token,
        ],
      ];
      $options = array_merge_recursive($headers, $options);
    }

    try {
      $response = $this->http->request(
        $method,
        $this->daxkoConfig->get('base_uri') . $uri,
        $options
      );
      return json_decode((string) $response->getBody());
    } catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }
  }

  /**
   * Execute GET request to Daxko API.
   *
   * @param string $uri
   *   Daxko API endpoint.
   *
   * @return mixed
   *   The successful response body or error.
   */
  public function getRequest(string $uri) {
    return $this->request('GET', $uri);
  }

  /**
   * Execute POST request to Daxko API.
   *
   * @param string $uri
   *   Daxko API endpoint.
   * @param array $body
   *   An array of items for the request body.
   *
   * @return mixed
   *   The successful response body or error.
   */
  public function postRequest($uri, array $body = []) {
    $options = [
      'body' => json_encode($body),
      'headers' => [
        'Content-Type' => 'application/json',
      ],
    ];
    return $this->request('POST', $uri, $options);
  }

  /**
   * @param $link
   *   Link with sso app.
   *
   * @return array
   *
   *   Whitelist our url at the Daxko SSO.
   */
  public function registerSSORedirectLink($link) {

    $token = $this->getDaxkoPartnerToken();

    $body = [
      'settings' => [
        'valid_redirect_uris' => [
          $link
        ],
        'links' => [
          'sign_up' => [
            'url' => 'https://operations.daxko.com/Online/Join.aspx?cid=' . $this->daxkoConfig->get('client_id'),
          ],
          'forgot_password' => [
            'url' => 'https://operations.daxko.com/online/' . $this->daxkoConfig->get('client_id') . '/Security/login.mvc/find_account',
          ],
        ],
      ]
    ];

    try {

      $response = $this->http->request('PUT', $this->daxkoConfig->get('base_uri') . 'partners/oauth2/members/settings',
        [
          'body' => json_encode($body),
          'headers' => [
            'Authorization' => "Bearer " . $token,
            'Content-Type' => 'application/json',
          ],
        ]);
    } catch (\Exception $exception) {
      return [
        'error' => 1,
        'message' => $exception->getMessage(),
      ];
    }

    return [
      'error' => 0,
      'message' => '',
      'response' => $response
    ];

  }

  /**
   * @param $code
   *   Access code from Daxko.
   * @param $redirect_url
   *   Redirect url at our website.
   *
   * @return mixed
   *
   *   Get access token based on user code after SSO redirect.
   */
  public function getUserAccessToken($code, $redirect_url) {

    try {
      $response = $this->http->request(
        'POST',
        $this->daxkoConfig->get('base_uri') . 'partners/oauth2/members/token',
        [
          'form_params' => [
            'grant_type' => 'authorization_code',
            'client_id' => $this->daxkoConfig->get('user'),
            'client_secret' => $this->daxkoConfig->get('pass'),
            'code' => $code,
            'redirect_uri' => $redirect_url,
          ],
          'headers' => [
            'Authorization' => "Bearer " . $this->daxkoConfig->get('referesh_token'),
          ],
        ]);

      return json_decode((string) $response->getBody())->access_token;
    } catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }

  }

  /**
   * @param $token
   *  User access token.
   *
   * @return array|mixed
   *
   *  Get ID of current user.
   */
  public function getMyInfo($token) {

    try {
      $response = $this->http->request(
        'GET',
        $this->daxkoConfig->get('base_uri') . 'members/me',
        [
          'headers' => [
            'Authorization' => "Bearer " . $token,
          ],
        ]);
      return json_decode((string) $response->getBody());
    } catch (\Exception $e) {
      return [
        'error' => 1,
        'message' => $e->getMessage(),
      ];
    }

  }

}
