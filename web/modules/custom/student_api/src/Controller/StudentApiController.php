<?php 

namespace Drupal\student_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class StudentApiController.
 */
class StudentApiController extends ControllerBase {

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new StudentApiController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Factory method for creating a new instance of the controller.
   * 
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * 
   * @return static
   *   A new instance of the controller.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * Returns a list of students with optional filters for stream, joining year, passing year, and name.
   * 
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response containing student data.
   */
  public function listStudents(Request $request) {
    $stream = $request->query->get('stream');
    $joining_year = $request->query->get('joining_year');
    $passing_year = $request->query->get('passing_year');
    $name = $request->query->get('name');

    $query = $this->database->select('users_field_data', 'u');
    $query->fields('u', ['uid', 'name', 'mail', 'created']);

    $query->join('user__roles', 'r', 'u.uid = r.entity_id');
    $query->condition('r.roles_target_id', 'student');

    $query->leftJoin('user__field_stream', 'fs', 'u.uid = fs.entity_id');
    $query->addField('fs', 'field_stream_target_id', 'stream_id');

    $query->leftJoin('taxonomy_term_field_data', 'tfd', 'fs.field_stream_target_id = tfd.tid');
    $query->addField('tfd', 'name', 'stream_name');

    $query->leftJoin('user__field_joining_year', 'jy', 'u.uid = jy.entity_id');
    $query->addField('jy', 'field_joining_year_value', 'joining_year_value');

    $query->leftJoin('user__field_passing_year', 'py', 'u.uid = py.entity_id');
    $query->addField('py', 'field_passing_year_value', 'passing_year_value');

    $query->leftJoin('user__field_student_id', 'si', 'u.uid = si.entity_id');
    $query->addField('si', 'field_student_id_value', 'student_id');

    if (!empty($stream)) {
      $query->condition('tfd.name', $stream);
    }
    if (!empty($joining_year)) {
      $query->condition('jy.field_joining_year_value', $joining_year);
    }
    if (!empty($passing_year)) {
      $query->condition('py.field_passing_year_value', $passing_year);
    }
    if (!empty($name)) {
      $query->condition('u.name', '%' . $this->database->escapeLike($name) . '%', 'LIKE');
    }

    $result = $query->execute()->fetchAll();

    $students = [];
    foreach ($result as $row) {
      $students[] = [
        'uid' => $row->uid,
        'name' => $row->name,
        'email' => $row->mail,
        'created' => date('Y-m-d', $row->created),
        'stream' => $row->stream_name ?? null,
        'joining_year' => $row->joining_year_value ?? null,
        'passing_year' => $row->passing_year_value ?? null,
        'student_id' => $row->student_id ?? null,
      ];
    }

    return new JsonResponse($students);
  }
}
