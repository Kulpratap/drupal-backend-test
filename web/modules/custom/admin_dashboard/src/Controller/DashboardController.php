<?php

namespace Drupal\admin_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class DashboardController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a DashboardController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Creates an instance of the DashboardController.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return \Drupal\admin_dashboard\Controller\DashboardController
   *   The instance of DashboardController.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Displays a list of inactive students based on date filter.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return array
   *   A render array for the inactive students list.
   */
  public function inactiveStudents(Request $request) {

    $year = $request->query->get('year', date('Y'));
    $month = $request->query->get('month', date('m'));

    $start_date = strtotime("$year-$month-01 00:00:00");
    $end_date = strtotime("$year-$month-" . date('t', $start_date) . " 23:59:59");

    $query = "SELECT u.uid, u.name, t.name AS stream
              FROM {users_field_data} u
              LEFT JOIN {user__field_stream} fs ON u.uid = fs.entity_id
              LEFT JOIN {taxonomy_term_field_data} t ON fs.field_stream_target_id = t.tid
              WHERE u.status = 0
                AND u.uid > 0
                AND u.created BETWEEN :start_date AND :end_date";

    $results = $this->database->query($query, [
      ':start_date' => $start_date,
      ':end_date' => $end_date,
    ])->fetchAll();

    $total_count = count($results);
    $items = [];
    foreach ($results as $student) {
      if (!empty($student->uid) && !empty($student->name)) {
        $stream = !empty($student->stream) ? $student->stream : 'N/A';
        $items[] = $student->uid . ' - ' . $student->name . ' - ' . $stream;
      }
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => $this->t('Inactive Students (Total: @count)', ['@count' => $total_count]),
    ];
  }
  
}
