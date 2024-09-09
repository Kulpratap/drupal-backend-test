<?php

namespace Drupal\student_activity\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Provides a block that displays the top 5 active students based on login counts.
 *
 * @Block(
 *   id = "top_active_students_block",
 *   admin_label = @Translation("Top Active Students"),
 * )
 */
class TopActiveStudentsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new TopActiveStudentsBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Creates an instance of this block using the service container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   A new instance of this block.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Builds the content for the Top Active Students block.
   *
   * Retrieves the top 5 active students based on login count.
   * login counts will displayef in this block.
   *
   * @return array
   *   A renderable array representing the block content.
   */
  public function build() {
    $query = "SELECT uid, COUNT(uid) AS login_count
              FROM {login_history}
              GROUP BY uid
              ORDER BY login_count DESC
              LIMIT 5";
    
    $results = $this->database->query($query)->fetchAllAssoc('uid');

    $uids = array_keys($results);
    $login_counts = array_map(function ($result) {
      return $result->login_count;
    }, $results);

    $users = [];
    if (!empty($uids)) {
      $users = $this->entityTypeManager->getStorage('user')->loadMultiple($uids);
    }

    $items = [];
    foreach ($users as $user) {
      if (in_array('student', $user->getRoles())) {
        $items[] = $user->getDisplayName() . ' - ' . $login_counts[$user->id()] . ' logins';
      }
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => $this->t('Top 5 Active Students'),
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
