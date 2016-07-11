<?php

namespace Drupal\persistent_login\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\persistent_login\TokenManager;

/**
 * Class UserTokens.
 *
 * @package Drupal\persistent_login\Controller
 */
class UserController extends ControllerBase {

  /**
   * The token manager service.
   *
   * @var \Drupal\persistent_login\TokenManager
   */
  protected $tokenManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    TokenManager $token_manager,
    ConfigFactoryInterface $config_factory,
    DateFormatterInterface $date_formatter
  ) {
    $this->tokenManager = $token_manager;
    $this->configFactory = $config_factory;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('persistent_login.token_manager'),
      $container->get('config.factory'),
      $container->get('date.formatter')
    );
  }

  /**
   * List users's active tokens.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account object.
   *
   * @return string Render array with list of user's active tokens.
   *   Render array with list of user's active tokens.
   */
  public function listTokens(UserInterface $user) {

    $config = $this->config('persistent_login.settings');
    $configuredLifetime = $config->get('lifetime');

    $render['tokens'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Created'),
        $this->t('Last Used'),
      ],
      '#empty' => $this->t('No persistent logins have been created.'),
    ];

    if ($configuredLifetime !== 0) {
      $render['tokens']['#header'][] = $this->t('Expires');
    }

    $tokens = $this->tokenManager->getTokensForUser($user);
    foreach ($tokens as $token) {
      $row = [
        'created' => [
          '#markup' => $this->dateFormatter->format($token->getCreated()->getTimestamp()),
        ],
        'refreshed' => [
          '#markup' => $this->dateFormatter->format($token->getRefreshed()->getTimestamp()),
        ],
      ];
      if ($configuredLifetime !== 0) {
        $row['expires'] = [
          '#markup' => $this->dateFormatter->format($token->getExpiry()->getTimestamp()),
        ];
      }

      $render['tokens'][] = $row;
    }

    return $render;
  }

}
