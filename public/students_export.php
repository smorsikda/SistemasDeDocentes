<?php
require __DIR__ . '/../app/config/db.php';
require __DIR__ . '/../app/middleware/auth.php';

requireLogin();
requirePasswordChangeIfNeeded();

$me = $_SESSION['user'];

$delim = ';';

$q      = trim((string)($_GET['q'] ?? ''));
$course = trim((string)($_GET['course_type'] ?? ''));
$level  = trim((string)($_GET['course_level'] ?? ''));
$dept   = trim((string)($_GET['department'] ?? ''));
$teacherId = (int)($_GET['teacher_id'] ?? 0);
$municipality = trim((string)($_GET['municipality'] ?? ''));
$organizationType = trim((string)($_GET['organization_type'] ?? ''));

$where = [];
$params = [];

if (($me['role'] ?? '') === 'teacher') {
  $where[] = "s.teacher_id = ?";
  $params[] = (int)$me['id'];
} else {
  if ($teacherId > 0) {
    $where[] = "s.teacher_id = ?";
    $params[] = $teacherId;
  }
}

if ($q !== '') {
  $where[] = "(
    s.full_name LIKE ? OR
    s.student_code LIKE ? OR
    s.cedula LIKE ? OR
    s.phone LIKE ? OR
    sc.name LIKE ? OR
    so.organization_name LIKE ? OR
    so.characterization LIKE ? OR
    s.profession LIKE ?
  )";
  $like = "%{$q}%";
  array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
}

if ($course !== '') {
  $where[] = "s.course_type = ?";
  $params[] = $course;
}

if ($level !== '') {
  $where[] = "s.course_level = ?";
  $params[] = $level;
}

if ($dept !== '') {
  $where[] = "d.name LIKE ?";
  $params[] = "%{$dept}%";
}

if ($municipality !== '') {
  $where[] = "m.name LIKE ?";
  $params[] = "%{$municipality}%";
}

if ($organizationType !== '') {
  $where[] = "so.organization_type = ?";
  $params[] = $organizationType;
}

$sqlWhere = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$sql = "
  SELECT
    s.id,
    s.student_code,
    s.full_name,
    s.sex,
    s.education_level,
    s.profession,
    s.nationality,
    sc.name AS school_name,
    s.course_type,
    s.course_level,
    s.phone,
    s.cedula,
    d.name AS department_name,
    m.name AS municipality_name,
    s.community,
    so.organization_type,
    so.organization_name,
    so.organization_phone,
    so.organization_location,
    so.characterization,
    so.trademark_registration,
    sp.course_purpose,
    so.number_of_members,
    sp.future_projection,
    s.enrolled_at,
    sp.observations,
    sp.notes,
    s.final_grade,
    s.status,
    u.name  AS teacher_name,
    u.email AS teacher_email,
    s.created_at,
    s.updated_at
  FROM students s
  JOIN users u ON u.id = s.teacher_id
  LEFT JOIN schools sc ON sc.id = s.school_id
  LEFT JOIN departments d ON d.id = s.department_id
  LEFT JOIN municipalities m ON m.id = s.municipality_id
  LEFT JOIN student_organizations so ON so.student_id = s.id
  LEFT JOIN student_profiles sp ON sp.student_id = s.id
  $sqlWhere
  ORDER BY s.created_at DESC, s.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$filename = "estudiantes_" . date("Y-m-d_H-i") . ".csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

function protectCsvCell($value): string
{
  $value = (string)($value ?? '');

  // Spreadsheet apps may evaluate cells beginning with formula operators.
  // A leading tab makes the value text in Excel-compatible CSV viewers.
  if (preg_match('/^[\t\r\n ]*[=+\-@]/u', $value)) {
    return "\t" . $value;
  }

  return $value;
}

fputcsv($out, [
  'ID',
  'Codigo',
  'Nombre',
  'Sexo',
  'Nivel_escolar',
  'Profesion',
  'Nacionalidad',
  'Escuela',
  'Curso',
  'Nivel',
  'Telefono',
  'Cedula',
  'Departamento',
  'Municipio',
  'Comunidad',
  'Tipo_organizacion',
  'Nombre_organizacion',
  'Telefono_organizacion',
  'Ubicacion_organizacion',
  'Caracterizacion',
  'Registro_marca',
  'Proposito_curso',
  'Numero_socios',
  'Proyeccion_futuro',
  'Fecha_inscripcion',
  'Observaciones',
  'Notas_internas',
  'Nota_final',
  'Estado',
  'Docente',
  'Docente_email',
  'Creado',
  'Actualizado'
], $delim);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $row = [
    $r['id'],
    $r['student_code'],
    $r['full_name'],
    $r['sex'],
    $r['education_level'],
    $r['profession'],
    $r['nationality'],
    $r['school_name'],
    $r['course_type'],
    $r['course_level'],
    $r['phone'],
    $r['cedula'],
    $r['department_name'],
    $r['municipality_name'],
    $r['community'],
    $r['organization_type'],
    $r['organization_name'],
    $r['organization_phone'],
    $r['organization_location'],
    $r['characterization'],
    $r['trademark_registration'],
    $r['course_purpose'],
    $r['number_of_members'],
    $r['future_projection'],
    $r['enrolled_at'],
    $r['observations'],
    $r['notes'],
    $r['final_grade'],
    $r['status'],
    $r['teacher_name'],
    $r['teacher_email'],
    $r['created_at'],
    $r['updated_at'],
  ];

  fputcsv($out, array_map('protectCsvCell', $row), $delim);
}

fclose($out);
exit;
