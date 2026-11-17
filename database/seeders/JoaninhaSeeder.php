<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\ClassroomPeriod;
use App\Enums\EducationStage;
use App\Enums\GuardianRelationship;
use App\Enums\SchoolEventType;
use App\Enums\SpecialEducationNeed;
use App\Enums\StaffRole;
use App\Enums\TeacherSpecialty;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\SchoolEvent;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Simula o uso real do sistema pela escola entre maio e novembro de 2026.
 *
 * - 18 turmas: duas por série (A de manhã, B à tarde), do Maternal I ao 5º ano, como numa escola pública de Infantil e Fundamental I.
 * - Equipe de escola pública: direção e vice-direção, coordenação, secretaria, auxiliares de classe no Maternal,
 *   regentes (uma por série no Infantil, exceto o Pré II B, e uma por turma no Fundamental), professora de AEE e especialistas
 *   de Inglês, Educação Física, Artes e Música.
 * - Cerca de 380 alunos (Fundamental com 23 a 28 por turma) com data de nascimento coerente com a série, responsável,
 *   informações de saúde e ~4,5% de público da Educação Especial.
 * - Educação Infantil avaliada por relatórios descritivos, sem nota (LDB, art. 31); Fundamental I com
 *   provas e trabalhos de 0 a 10 seguindo os bimestres.
 *
 * Os dados são determinísticos (semente fixa), então rodar o seeder novamente gera o mesmo resultado.
 */
class JoaninhaSeeder extends Seeder
{
    use WithoutModelEvents;

    private const string SCHOOL_YEAR = '2026';

    /** Semana em que a escola começou a usar o sistema. */
    private const string IMPLANTATION_DATE = '2026-05-11';

    /** "Hoje" na simulação: avaliações após esta data ainda não têm notas lançadas. */
    private const string REFERENCE_DATE = '2026-11-14 18:00';

    private const float ABSENCE_CHANCE = 0.04;

    private const float COMMENT_CHANCE = 0.15;

    private const float ALLERGY_CHANCE = 0.12;

    private const float HEALTH_NOTE_CHANCE = 0.10;

    /** Domínio fictício usado nos e-mails simulados de responsáveis. */
    private const string GUARDIAN_EMAIL_DOMAIN = 'exemplo.com.br';

    private const string TEACHER_EMAIL_DOMAIN = 'escolajoaninha.com.br';

    /** Senha temporária dos logins de exemplo; cada pessoa deve trocá-la na página de Perfil. */
    private const string DEMO_PASSWORD = 'Joaninha@2026';

    private Randomizer $randomizer;

    private CarbonImmutable $referenceDate;

    private int $registrationSequence = 0;

    /** @var array<string, true> */
    private array $usedStudentNames = [];

    /** @var array<int, float> Desempenho médio esperado de cada aluno, indexado pelo id. */
    private array $studentAbilities = [];

    /** @var array<string, StaffMember> Professores especialistas, indexados pelo valor de TeacherSpecialty. */
    private array $specialists = [];

    /** @var array<string, StaffMember> Auxiliares de classe, indexadas pelo nome da turma que acompanham. */
    private array $assistants = [];

    public function run(): void
    {
        $this->randomizer = new Randomizer(new Mt19937(2026));
        $this->referenceDate = CarbonImmutable::parse(self::REFERENCE_DATE);

        $homeroomTeachers = $this->createTeachers();

        foreach ($this->classroomDefinitions() as $index => $definition) {
            $homeroomTeacher = $homeroomTeachers[$definition['homeroom']];
            $classroom = $this->createClassroom($definition, $homeroomTeacher);
            $this->attachSpecialists($classroom, $definition);
            $students = $this->createStudents($classroom, $definition);

            if (collect($students)->contains(fn (Student $student): bool => $student->isSpecialEducation())) {
                $classroom->teachers()->attach($this->specialists[TeacherSpecialty::SpecialEducation->value], ['created_at' => $classroom->created_at, 'updated_at' => $classroom->created_at]);
            }

            $assessments = $definition['grade'] === null
                ? $this->earlyChildhoodAssessments()
                : $this->elementaryAssessments($definition['grade']);

            foreach ($assessments as $assessment) {
                $teacher = $assessment['teacher'] === 'homeroom' ? $homeroomTeacher : $this->specialists[$assessment['teacher']];

                $this->createExamWithGrades($classroom, $students, $assessment, $definition, $teacher, dayOffset: $index % 5);
            }
        }

        $this->createAdministrativeStaffAndLogins();
        $this->createSchoolEvents();
        $this->createAttendanceRecords();
    }

    /**
     * Calendário do ano letivo de 2026: feriados, recessos, reuniões, conselhos de classe, festas e passeios.
     */
    private function createSchoolEvents(): void
    {
        $classroomId = fn (string $name): int => Classroom::query()->where('name', $name)->value('id');

        $events = [
            ['Início do ano letivo', SchoolEventType::Other, '2026-02-02', null, null, 'Acolhimento das famílias no primeiro dia de aula.'],
            ['Carnaval', SchoolEventType::Holiday, '2026-02-16', '2026-02-18', null, 'Retorno às 13h na quarta-feira de cinzas.'],
            ['Sexta-feira Santa', SchoolEventType::Holiday, '2026-04-03', null, null, null],
            ['Tiradentes', SchoolEventType::Holiday, '2026-04-21', null, null, null],
            ['Reunião de pais – 1º bimestre', SchoolEventType::ParentsMeeting, '2026-04-25', null, null, 'Entrega dos boletins do 1º bimestre, das 8h às 11h.'],
            ['Dia do Trabalho', SchoolEventType::Holiday, '2026-05-01', null, null, null],
            ['Homenagem ao Dia das Mães', SchoolEventType::Celebration, '2026-05-09', null, null, 'Café da manhã com as famílias.'],
            ['Implantação do sistema Joaninha', SchoolEventType::Other, '2026-05-11', null, null, 'Treinamento da equipe e cadastro inicial das turmas e alunos.'],
            ['Corpus Christi', SchoolEventType::Holiday, '2026-06-04', null, null, null],
            ['Emenda de Corpus Christi', SchoolEventType::PedagogicalDay, '2026-06-05', null, null, 'Dia de planejamento da equipe, sem aula.'],
            ['Festa Junina', SchoolEventType::Celebration, '2026-06-20', null, null, 'Quadrilhas das turmas a partir das 10h.'],
            ['Reunião de pais – 2º bimestre', SchoolEventType::ParentsMeeting, '2026-07-04', null, null, 'Entrega dos boletins e relatórios do 1º semestre.'],
            ['Conselho de classe – 2º bimestre', SchoolEventType::ClassCouncil, '2026-07-09', null, null, null],
            ['Recesso escolar de julho', SchoolEventType::Recess, '2026-07-13', '2026-07-31', null, null],
            ['Planejamento do 2º semestre', SchoolEventType::PedagogicalDay, '2026-08-03', null, null, 'Formação da equipe, sem aula.'],
            ['Festa da Família', SchoolEventType::Celebration, '2026-08-08', null, null, 'Homenagem ao Dia dos Pais com gincanas.'],
            ['Desfile do Folclore', SchoolEventType::Celebration, '2026-08-21', null, null, 'Apresentação dos projetos de folclore no pátio.'],
            ['Independência do Brasil', SchoolEventType::Holiday, '2026-09-07', null, null, null],
            ['Conselho de classe – 3º bimestre', SchoolEventType::ClassCouncil, '2026-10-01', null, null, null],
            ['Reunião de pais – 3º bimestre', SchoolEventType::ParentsMeeting, '2026-10-03', null, null, null],
            ['Semana da Criança', SchoolEventType::Celebration, '2026-10-05', '2026-10-09', null, 'Brincadeiras, cinema e piquenique em cada dia da semana.'],
            ['Nossa Senhora Aparecida', SchoolEventType::Holiday, '2026-10-12', null, null, null],
            ['Dia do Professor', SchoolEventType::PedagogicalDay, '2026-10-15', null, null, 'Sem aula.'],
            ['Passeio ao Zoológico', SchoolEventType::FieldTrip, '2026-10-22', null, $classroomId('Pré II A'), 'Saída às 8h, retorno às 12h. Enviar lanche e boné.'],
            ['Passeio ao Zoológico', SchoolEventType::FieldTrip, '2026-10-22', null, $classroomId('Pré II B'), 'Saída às 13h, retorno às 17h. Enviar lanche e boné.'],
            ['Finados', SchoolEventType::Holiday, '2026-11-02', null, null, null],
            ['Estudo do meio – Museu Catavento', SchoolEventType::FieldTrip, '2026-11-11', null, $classroomId('4º Ano A'), 'Autorização assinada pelos responsáveis até 06/11.'],
            ['Estudo do meio – Museu Catavento', SchoolEventType::FieldTrip, '2026-11-12', null, $classroomId('4º Ano B'), 'Autorização assinada pelos responsáveis até 06/11.'],
            ['Proclamação da República', SchoolEventType::Holiday, '2026-11-15', null, null, null],
            ['Dia da Consciência Negra', SchoolEventType::Holiday, '2026-11-20', null, null, null],
            ['Mostra Cultural e Feira do Livro', SchoolEventType::Celebration, '2026-11-28', null, null, 'Exposição dos trabalhos do ano, das 9h às 13h.'],
            ['Reunião de pais – entrega dos boletins finais', SchoolEventType::ParentsMeeting, '2026-12-05', null, null, null],
            ['Conselho de classe final', SchoolEventType::ClassCouncil, '2026-12-10', null, null, null],
            ['Formatura do Pré II', SchoolEventType::Celebration, '2026-12-12', null, null, 'Cerimônia no auditório às 10h.'],
            ['Encerramento do ano letivo', SchoolEventType::Other, '2026-12-18', null, null, null],
            ['Natal', SchoolEventType::Holiday, '2026-12-25', null, null, null],
        ];

        $createdAt = CarbonImmutable::parse(self::IMPLANTATION_DATE.' 10:00:00');

        foreach ($events as [$title, $type, $startsOn, $endsOn, $classroom, $description]) {
            $event = new SchoolEvent;
            $event->forceFill([
                'title' => $title,
                'type' => $type,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'classroom_id' => $classroom,
                'description' => $description,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }
    }

    /**
     * Chamada de todos os dias letivos desde a implantação. A maioria dos alunos falta pouco, alguns
     * faltam com frequência (para os alertas de frequência), e as faltas em dia de prova batem com o diário.
     */
    private function createAttendanceRecords(): void
    {
        $firstDay = CarbonImmutable::parse(self::IMPLANTATION_DATE);
        $lastDay = $this->referenceDate->startOfDay();
        $daysWithoutClasses = SchoolEvent::daysWithoutClasses($firstDay, $lastDay);

        $schoolDays = [];

        for ($day = $firstDay; $day->lte($lastDay); $day = $day->addDay()) {
            if (! $day->isWeekend() && ! isset($daysWithoutClasses[$day->toDateString()])) {
                $schoolDays[] = $day;
            }
        }

        $rows = [];

        foreach (Student::query()->with('classroom.homeroomTeacher')->orderBy('id')->get() as $student) {
            $absenceChance = $this->chance(0.06) ? $this->randomizer->getFloat(0.16, 0.32) : $this->randomizer->getFloat(0.01, 0.07);
            $recordedBy = $student->classroom->homeroomTeacher?->user_id;
            $time = $student->classroom->period === ClassroomPeriod::Morning ? '07:40:00' : '13:10:00';

            foreach ($schoolDays as $day) {
                if ($student->created_at->startOfDay()->gt($day)) {
                    continue;
                }

                $status = match (true) {
                    $this->chance($absenceChance) => $this->chance(0.35) ? AttendanceStatus::Justified : AttendanceStatus::Absent,
                    $this->chance(0.02) => AttendanceStatus::Late,
                    default => AttendanceStatus::Present,
                };

                $timestamp = $day->toDateString().' '.$time;

                $rows[] = [
                    'classroom_id' => $student->classroom_id,
                    'student_id' => $student->id,
                    'date' => $day->toDateString(),
                    'status' => $status->value,
                    'notes' => $status === AttendanceStatus::Justified ? 'Atestado médico entregue na secretaria.' : null,
                    'recorded_by' => $recordedBy,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                if (count($rows) === 1000) {
                    Attendance::query()->insert($rows);
                    $rows = [];
                }
            }
        }

        Attendance::query()->insert($rows);

        $this->alignAttendanceWithExamAbsences();
    }

    /**
     * Quem faltou à prova (inclusive quem fez segunda chamada) aparece como ausente na chamada daquele dia.
     */
    private function alignAttendanceWithExamAbsences(): void
    {
        Grade::query()
            ->with('exam')
            ->where(fn ($query) => $query->where('comments', 'like', 'Faltou%')->orWhere('comments', 'like', 'Realizou a segunda chamada%'))
            ->get()
            ->each(function (Grade $grade): void {
                $status = str_contains((string) $grade->comments, 'atestado') ? AttendanceStatus::Justified : AttendanceStatus::Absent;

                Attendance::query()
                    ->where('student_id', $grade->student_id)
                    ->whereDate('date', $grade->exam->applied_at)
                    ->update([
                        'status' => $status,
                        'notes' => $status === AttendanceStatus::Justified ? 'Atestado médico entregue na secretaria.' : null,
                    ]);
            });
    }

    /**
     * Direção, coordenação e secretaria, além dos logins de exemplo de cada cargo.
     * Usa apenas valores fixos (sem a sequência aleatória), para não alterar os demais dados simulados.
     */
    private function createAdministrativeStaffAndLogins(): void
    {
        $staff = [
            ['Sônia', 'sonia@'.self::TEACHER_EMAIL_DOMAIN, '(11) 99120-4410', StaffRole::Direction, '2009-01-12'],
            ['Roberto Carvalho Mendes', 'roberto.mendes@'.self::TEACHER_EMAIL_DOMAIN, '(11) 99455-0321', StaffRole::ViceDirection, '2012-02-06'],
            ['Helena Prado Martins', 'helena.martins@'.self::TEACHER_EMAIL_DOMAIN, '(11) 98803-2251', StaffRole::Coordination, '2014-02-03'],
            ['Márcia Oliveira Santos', 'marcia.santos@'.self::TEACHER_EMAIL_DOMAIN, '(11) 97740-1189', StaffRole::Secretary, '2016-07-18'],
        ];

        foreach ($staff as [$name, $email, $phone, $role, $hiredAt]) {
            $createdAt = CarbonImmutable::parse(self::IMPLANTATION_DATE.' 08:30:00');

            $member = new StaffMember;
            $member->forceFill([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'role' => $role,
                'hired_at' => $hiredAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
        }

        foreach (['sonia', 'roberto.mendes', 'helena.martins', 'marcia.santos', 'claudia.rossini'] as $login) {
            $member = StaffMember::query()->where('email', "{$login}@".self::TEACHER_EMAIL_DOMAIN)->firstOrFail();
            $user = User::query()->firstOrNew(['email' => $member->email]);
            $user->forceFill([
                'name' => $member->name,
                'password' => $user->exists ? $user->password : self::DEMO_PASSWORD,
                'email_verified_at' => $user->email_verified_at ?? $member->created_at,
                'created_at' => $user->created_at ?? $member->created_at,
                'updated_at' => $user->updated_at ?? $member->created_at,
            ])->save();

            $member->user()->associate($user)->save();
        }
    }

    /**
     * Cria as 13 regentes e os 5 especialistas. Retorna as regentes indexadas pela chave usada nas turmas.
     *
     * @return array<string, StaffMember>
     */
    private function createTeachers(): array
    {
        $homeroomNames = [
            'maternal-1' => 'Mariana Lopes Ferreira',
            'maternal-2' => 'Patrícia Andrade Souza',
            'pre-1' => 'Juliana Ribeiro Costa',
            'pre-2' => 'Fernanda Almeida Rocha',
            '1-A' => 'Camila Duarte Nogueira',
            '1-B' => 'Aline Martins Barros',
            '2-A' => 'Renata Carvalho Pires',
            '2-B' => 'Luciana Freitas Moura',
            '3-A' => 'Tatiane Gomes Vieira',
            '3-B' => 'Carolina Mendes Pacheco',
            '4-A' => 'Roberta Lima Cardoso',
            '4-B' => 'Daniela Teixeira Campos',
            '5-A' => 'Simone Araújo Lopes',
            '5-B' => 'Vanessa Rocha Dantas',
        ];

        $homeroomTeachers = [];

        foreach ($homeroomNames as $key => $name) {
            $homeroomTeachers[$key] = $this->createTeacher($name, TeacherSpecialty::Pedagogy);
        }

        $specialists = [
            [TeacherSpecialty::English, 'Rafael Monteiro Dias'],
            [TeacherSpecialty::PhysicalEducation, 'Thiago Nascimento Prado'],
            [TeacherSpecialty::Arts, 'Gabriela Fonseca Lins'],
            [TeacherSpecialty::Music, 'André Matos Siqueira'],
        ];

        foreach ($specialists as [$specialty, $name]) {
            $this->specialists[$specialty->value] = $this->createTeacher($name, $specialty);
        }

        $this->specialists['physical_education_early'] = $this->createTeacher('Bruna Cavalcanti Reis', TeacherSpecialty::PhysicalEducation);
        $this->specialists[TeacherSpecialty::SpecialEducation->value] = $this->createTeacher('Luana Ferraz Monteiro', TeacherSpecialty::SpecialEducation);

        foreach (['Maternal I A' => 'Edna Pereira Souza', 'Maternal I B' => 'Rosângela Martins Silva', 'Maternal II A' => 'Joana Batista Lima', 'Maternal II B' => 'Priscila Moura Santos'] as $classroomName => $assistantName) {
            $this->assistants[$classroomName] = $this->createTeacher($assistantName, null, StaffRole::Assistant);
        }

        $homeroomTeachers['pre-2-B'] = $this->createFixedTeacher(
            name: 'Claudia Elaine Rossini',
            email: 'claudia.rossini@'.self::TEACHER_EMAIL_DOMAIN,
            phone: '(11) 98412-3307',
            hiredAt: '2018-02-05',
        );

        return $homeroomTeachers;
    }

    /**
     * Professora cadastrada com dados fixos, sem consumir a sequência aleatória:
     * assim os demais dados simulados continuam idênticos aos de antes dela.
     */
    private function createFixedTeacher(string $name, string $email, string $phone, string $hiredAt): StaffMember
    {
        $createdAt = CarbonImmutable::parse(self::IMPLANTATION_DATE.' 08:45:00');

        $teacher = new StaffMember;
        $teacher->forceFill([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => StaffRole::Teacher,
            'specialty' => TeacherSpecialty::Pedagogy,
            'hired_at' => $hiredAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $teacher;
    }

    private function createTeacher(string $name, ?TeacherSpecialty $specialty, StaffRole $role = StaffRole::Teacher): StaffMember
    {
        $parts = explode(' ', Str::lower(Str::ascii($name)));
        $createdAt = $this->randomTime(CarbonImmutable::parse(self::IMPLANTATION_DATE), 8, 9);

        $teacher = new StaffMember;
        $teacher->forceFill([
            'name' => $name,
            'email' => $parts[0].'.'.end($parts).'@'.self::TEACHER_EMAIL_DOMAIN,
            'phone' => $this->phoneNumber(),
            'role' => $role,
            'specialty' => $specialty,
            'hired_at' => CarbonImmutable::create($this->randomizer->getInt(2012, 2025), $this->pick([2, 2, 2, 7, 8]), $this->randomizer->getInt(1, 20))->toDateString(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $teacher;
    }

    /**
     * Duas turmas por série: A de manhã e B à tarde.
     *
     * @return list<array{name: string, period: string, grade: int|null, age: int, students: int, homeroom: string}>
     */
    private function classroomDefinitions(): array
    {
        $series = [
            ['name' => 'Maternal I', 'grade' => null, 'age' => 2, 'students' => 11, 'homeroom' => 'maternal-1'],
            ['name' => 'Maternal II', 'grade' => null, 'age' => 3, 'students' => 13, 'homeroom' => 'maternal-2'],
            ['name' => 'Pré I', 'grade' => null, 'age' => 4, 'students' => 15, 'homeroom' => 'pre-1'],
            ['name' => 'Pré II', 'grade' => null, 'age' => 5, 'students' => 16, 'homeroom' => 'pre-2', 'homeroom_b' => 'pre-2-B'],
            ['name' => '1º Ano', 'grade' => 1, 'age' => 6, 'students' => 25, 'homeroom' => '1'],
            ['name' => '2º Ano', 'grade' => 2, 'age' => 7, 'students' => 26, 'homeroom' => '2'],
            ['name' => '3º Ano', 'grade' => 3, 'age' => 8, 'students' => 26, 'homeroom' => '3'],
            ['name' => '4º Ano', 'grade' => 4, 'age' => 9, 'students' => 27, 'homeroom' => '4'],
            ['name' => '5º Ano', 'grade' => 5, 'age' => 10, 'students' => 27, 'homeroom' => '5'],
        ];

        $definitions = [];

        foreach ($series as $serie) {
            foreach (['A' => 'morning', 'B' => 'afternoon'] as $letter => $period) {
                $definitions[] = [
                    ...$serie,
                    'name' => "{$serie['name']} {$letter}",
                    'period' => $period,
                    'students' => $serie['students'] + $this->randomizer->getInt(-2, 1),
                    'homeroom' => match (true) {
                        $serie['grade'] !== null => "{$serie['homeroom']}-{$letter}",
                        $letter === 'B' && isset($serie['homeroom_b']) => $serie['homeroom_b'],
                        default => $serie['homeroom'],
                    },
                ];
            }
        }

        return $definitions;
    }

    /**
     * Especialistas por etapa: Música e Ed. Física infantil no Infantil; Inglês e Artes a partir do Pré;
     * Ed. Física do Fundamental nas turmas de 1º a 4º ano.
     *
     * @param  array{name: string, period: string, grade: int|null, age: int, students: int, homeroom: string}  $definition
     */
    private function attachSpecialists(Classroom $classroom, array $definition): void
    {
        $isElementary = $definition['grade'] !== null;
        $isPreschoolOrAbove = $definition['age'] >= 4;

        $teachers = array_filter([
            $isPreschoolOrAbove ? $this->specialists[TeacherSpecialty::English->value] : null,
            $isPreschoolOrAbove ? $this->specialists[TeacherSpecialty::Arts->value] : null,
            $isElementary ? $this->specialists[TeacherSpecialty::PhysicalEducation->value] : $this->specialists['physical_education_early'],
            $isElementary ? null : $this->specialists[TeacherSpecialty::Music->value],
        ]);

        $classroom->teachers()->attach(
            collect($teachers)->mapWithKeys(fn (StaffMember $teacher): array => [
                $teacher->id => ['created_at' => $classroom->created_at, 'updated_at' => $classroom->created_at],
            ])->all()
        );

        if (isset($this->assistants[$definition['name']])) {
            $classroom->assistants()->attach($this->assistants[$definition['name']], ['created_at' => $classroom->created_at, 'updated_at' => $classroom->created_at]);
        }
    }

    /**
     * Calendário de avaliações do Fundamental I dentro do período simulado (recesso em julho).
     *
     * @return list<array{week: string, title: string, weight: float, kind: string, teacher: string}>
     */
    private function elementaryAssessments(int $grade): array
    {
        $writingAssessment = $grade <= 2 ? 'Ditado e Leitura – 3º Bimestre' : 'Produção de Texto – 3º Bimestre';

        return [
            ['week' => '2026-05-25', 'title' => 'Trabalho – Projeto Leitura em Família', 'weight' => 1.0, 'kind' => 'graded', 'teacher' => 'homeroom'],
            ['week' => '2026-06-08', 'title' => 'Avaliação de Inglês – 2º Bimestre', 'weight' => 1.0, 'kind' => 'graded', 'teacher' => TeacherSpecialty::English->value],
            ['week' => '2026-06-15', 'title' => 'Avaliação de Matemática – 2º Bimestre', 'weight' => 2.0, 'kind' => 'graded', 'teacher' => 'homeroom'],
            ['week' => '2026-06-22', 'title' => 'Avaliação de Português – 2º Bimestre', 'weight' => 2.0, 'kind' => 'graded', 'teacher' => 'homeroom'],
            ['week' => '2026-06-29', 'title' => 'Avaliação de Ciências – 2º Bimestre', 'weight' => 1.0, 'kind' => 'graded', 'teacher' => 'homeroom'],
            ['week' => '2026-08-17', 'title' => 'Trabalho – Folclore Brasileiro', 'weight' => 1.0, 'kind' => 'graded', 'teacher' => 'homeroom'],
            ['week' => '2026-08-24', 'title' => 'Trabalho de Artes – Autorretrato', 'weight' => 1.0, 'kind' => 'graded', 'teacher' => TeacherSpecialty::Arts->value],
            ['week' => '2026-08-31', 'title' => $writingAssessment, 'weight' => 1.0, 'kind' => 'graded', 'teacher' => 'homeroom'],
            ['week' => '2026-09-08', 'title' => 'Avaliação de Inglês – 3º Bimestre', 'weight' => 1.0, 'kind' => 'graded', 'teacher' => TeacherSpecialty::English->value],
            ['week' => '2026-09-14', 'title' => 'Avaliação de Matemática – 3º Bimestre', 'weight' => 2.0, 'kind' => 'graded', 'teacher' => 'homeroom'],
            ['week' => '2026-09-21', 'title' => 'Avaliação de Português – 3º Bimestre', 'weight' => 2.0, 'kind' => 'graded', 'teacher' => 'homeroom'],
            ['week' => '2026-09-28', 'title' => 'Trabalho – Feira de Ciências', 'weight' => 1.0, 'kind' => 'graded', 'teacher' => 'homeroom'],
            ['week' => '2026-10-19', 'title' => 'Educação Física – Avaliação Prática', 'weight' => 1.0, 'kind' => 'graded', 'teacher' => TeacherSpecialty::PhysicalEducation->value],
            ['week' => '2026-10-26', 'title' => 'Avaliação de História e Geografia – 4º Bimestre', 'weight' => 1.0, 'kind' => 'graded', 'teacher' => 'homeroom'],
            ['week' => '2026-11-09', 'title' => 'Avaliação de Matemática – 4º Bimestre', 'weight' => 2.0, 'kind' => 'graded', 'teacher' => 'homeroom'],
            ['week' => '2026-11-16', 'title' => 'Avaliação de Inglês – 4º Bimestre', 'weight' => 1.0, 'kind' => 'graded', 'teacher' => TeacherSpecialty::English->value],
            ['week' => '2026-11-23', 'title' => 'Avaliação de Português – 4º Bimestre', 'weight' => 2.0, 'kind' => 'graded', 'teacher' => 'homeroom'],
        ];
    }

    /**
     * Registros da Educação Infantil: relatórios semestrais e observações de projetos, sem nota.
     *
     * @return list<array{week: string, title: string, weight: float, kind: string, teacher: string}>
     */
    private function earlyChildhoodAssessments(): array
    {
        return [
            ['week' => '2026-06-22', 'title' => 'Relatório de Desenvolvimento – 1º Semestre', 'weight' => 1.0, 'kind' => 'report', 'teacher' => 'homeroom'],
            ['week' => '2026-08-17', 'title' => 'Registro de Observação – Projeto Folclore', 'weight' => 1.0, 'kind' => 'observation', 'teacher' => 'homeroom'],
            ['week' => '2026-10-05', 'title' => 'Registro de Observação – Semana da Criança', 'weight' => 1.0, 'kind' => 'observation', 'teacher' => 'homeroom'],
            ['week' => '2026-11-30', 'title' => 'Relatório de Desenvolvimento – 2º Semestre', 'weight' => 1.0, 'kind' => 'report', 'teacher' => 'homeroom'],
        ];
    }

    /**
     * @param  array{name: string, period: string, grade: int|null, age: int, students: int, homeroom: string}  $definition
     */
    private function createClassroom(array $definition, StaffMember $homeroomTeacher): Classroom
    {
        $createdAt = $this->randomTime(CarbonImmutable::parse(self::IMPLANTATION_DATE), 9, 10);

        $classroom = new Classroom;
        $classroom->forceFill([
            'name' => $definition['name'],
            'stage' => $definition['grade'] === null ? EducationStage::EarlyChildhood : EducationStage::Elementary,
            'year' => self::SCHOOL_YEAR,
            'period' => ClassroomPeriod::from($definition['period']),
            'homeroom_teacher_id' => $homeroomTeacher->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $classroom;
    }

    /**
     * Cria os alunos do cadastro inicial (semana da implantação) e eventuais transferências ao longo do semestre.
     *
     * @param  array{name: string, period: string, grade: int|null, age: int, students: int, homeroom: string}  $definition
     * @return list<Student>
     */
    private function createStudents(Classroom $classroom, array $definition): array
    {
        $students = [];
        $implantation = CarbonImmutable::parse(self::IMPLANTATION_DATE);
        $maximumYearsInSchool = min(max($definition['age'] - 2, 0), 5);

        for ($i = 0; $i < $definition['students']; $i++) {
            $enrollmentYear = (int) self::SCHOOL_YEAR - $this->randomizer->getInt(0, $maximumYearsInSchool);
            $createdAt = $this->randomTime($this->nextWeekday($implantation->addDays($this->randomizer->getInt(0, 4))), 8, 17);

            $students[] = $this->createStudent($classroom, $definition['age'], $enrollmentYear, $createdAt);
        }

        $transfers = $this->randomizer->getInt(0, 2);

        for ($i = 0; $i < $transfers; $i++) {
            $enrolledOn = CarbonImmutable::parse('2026-06-01')->addDays($this->randomizer->getInt(0, 135));

            if ($enrolledOn->month === 7) {
                $enrolledOn = $enrolledOn->setDate(2026, 8, $this->randomizer->getInt(3, 14));
            }

            $students[] = $this->createStudent($classroom, $definition['age'], (int) self::SCHOOL_YEAR, $this->randomTime($this->nextWeekday($enrolledOn), 8, 17));
        }

        return $students;
    }

    private function createStudent(Classroom $classroom, int $age, int $enrollmentYear, CarbonImmutable $createdAt): Student
    {
        $name = $this->uniqueStudentName();

        $student = new Student;
        $student->forceFill([
            'classroom_id' => $classroom->id,
            'name' => $name,
            'registration_number' => sprintf('%d%04d', $enrollmentYear, ++$this->registrationSequence),
            'birth_date' => $this->birthDateForAge($age),
            ...$this->guardianAttributes($name),
            ...$this->healthAttributes(),
            ...$this->specialEducationAttributes(),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $this->studentAbilities[$student->id] = round(
            ($this->randomizer->getFloat(3.5, 10.0) + $this->randomizer->getFloat(5.0, 10.0)) / 2,
            2,
        );

        return $student;
    }

    /**
     * Data de corte de 31 de março: completa a idade da série até essa data do ano letivo.
     */
    private function birthDateForAge(int $age): string
    {
        $earliest = CarbonImmutable::create((int) self::SCHOOL_YEAR - $age - 1, 4, 1);

        return $earliest->addDays($this->randomizer->getInt(0, 364))->toDateString();
    }

    /**
     * @return array{guardian_name: string, guardian_relationship: GuardianRelationship, guardian_phone: string, guardian_email: string}
     */
    private function guardianAttributes(string $studentName): array
    {
        $relationship = $this->pick([
            ...array_fill(0, 13, GuardianRelationship::Mother),
            ...array_fill(0, 4, GuardianRelationship::Father),
            GuardianRelationship::Grandmother,
            GuardianRelationship::Grandmother,
            $this->pick([GuardianRelationship::Grandfather, GuardianRelationship::Aunt, GuardianRelationship::Uncle, GuardianRelationship::LegalGuardian]),
        ]);

        $isFemale = in_array($relationship, [GuardianRelationship::Mother, GuardianRelationship::Grandmother, GuardianRelationship::Aunt], true)
            || ($relationship === GuardianRelationship::LegalGuardian && $this->chance(0.5));

        $firstName = $this->pick($isFemale
            ? ['Ana Paula', 'Juliana', 'Fernanda', 'Patrícia', 'Aline', 'Camila', 'Priscila', 'Vanessa', 'Tatiane', 'Renata', 'Simone', 'Luciana', 'Daniela', 'Cristiane', 'Michele', 'Carla', 'Débora', 'Viviane', 'Elaine', 'Raquel', 'Sandra', 'Márcia', 'Adriana', 'Bianca', 'Larissa']
            : ['Rodrigo', 'Marcelo', 'Fábio', 'Anderson', 'Leandro', 'Diego', 'Gustavo', 'Eduardo', 'Ricardo', 'Thiago', 'Bruno', 'Alexandre', 'Fernando', 'Márcio', 'Paulo', 'Sérgio', 'Vinícius', 'Renato']);

        $surname = Str::afterLast($studentName, ' ');
        $guardianName = "{$firstName} {$surname}";
        $emailUser = Str::of(Str::ascii($guardianName))->lower()->replace(' ', '.')->toString();

        return [
            'guardian_name' => $guardianName,
            'guardian_relationship' => $relationship,
            'guardian_phone' => $this->phoneNumber(),
            'guardian_email' => $emailUser.($this->chance(0.4) ? $this->randomizer->getInt(1, 99) : '').'@'.self::GUARDIAN_EMAIL_DOMAIN,
        ];
    }

    /**
     * Cerca de 4,5% dos alunos são público da Educação Especial (média nacional da rede pública: ~4%).
     *
     * @return array{special_education: SpecialEducationNeed|null, special_education_notes: string|null}
     */
    private function specialEducationAttributes(): array
    {
        if (! $this->chance(0.045)) {
            return ['special_education' => null, 'special_education_notes' => null];
        }

        $need = $this->pick([
            SpecialEducationNeed::Autism, SpecialEducationNeed::Autism, SpecialEducationNeed::Autism, SpecialEducationNeed::Autism,
            SpecialEducationNeed::IntellectualDisability, SpecialEducationNeed::IntellectualDisability, SpecialEducationNeed::IntellectualDisability,
            SpecialEducationNeed::PhysicalDisability, SpecialEducationNeed::HearingImpairment, SpecialEducationNeed::VisualImpairment, SpecialEducationNeed::Giftedness,
        ]);

        return [
            'special_education' => $need,
            'special_education_notes' => match ($need) {
                SpecialEducationNeed::Autism => 'Antecipar mudanças na rotina com apoio visual; abafador de ruído disponível na sala. AEE no contraturno, duas vezes por semana.',
                SpecialEducationNeed::IntellectualDisability => 'Atividades e avaliações adaptadas, com enunciados curtos e material concreto. AEE no contraturno.',
                SpecialEducationNeed::PhysicalDisability => 'Usa cadeira de rodas; mesa adaptada e acesso pela rampa lateral. Apoio de cuidador na troca e na alimentação.',
                SpecialEducationNeed::HearingImpairment => 'Usa aparelho auditivo; sentar próximo à professora e falar de frente. Acompanhamento em Libras no AEE.',
                SpecialEducationNeed::VisualImpairment => 'Baixa visão: material ampliado (fonte 24) e lupa. Sentar nas primeiras fileiras, longe do reflexo da janela.',
                SpecialEducationNeed::Giftedness => 'Enriquecimento curricular com projetos de pesquisa no AEE.',
            },
        ];
    }

    /**
     * @return array{allergies: string|null, health_notes: string|null}
     */
    private function healthAttributes(): array
    {
        return [
            'allergies' => $this->chance(self::ALLERGY_CHANCE)
                ? $this->pick(['Lactose', 'Amendoim', 'Glúten', 'Ovo', 'Corante amarelo (tartrazina)', 'Dipirona', 'Picada de inseto', 'Camarão e frutos do mar', 'Penicilina'])
                : null,
            'health_notes' => $this->chance(self::HEALTH_NOTE_CHANCE)
                ? $this->pick([
                    'Asma leve: usa bombinha quando necessário (fica na mochila).',
                    'Usa óculos; sentar nas primeiras fileiras.',
                    'Dermatite atópica: usar protetor solar próprio nas atividades ao ar livre.',
                    'Em acompanhamento fonoaudiológico.',
                    'TDAH em acompanhamento; família pede atividades curtas e combinados visuais.',
                    'Não pode tomar sol prolongado (orientação médica).',
                    'Rinite alérgica: evitar contato com poeira e giz.',
                ])
                : null,
        ];
    }

    private function phoneNumber(): string
    {
        return sprintf('(11) 9%04d-%04d', $this->randomizer->getInt(5000, 9999), $this->randomizer->getInt(0, 9999));
    }

    /**
     * @param  list<Student>  $students
     * @param  array{week: string, title: string, weight: float, kind: string, teacher: string}  $assessment
     * @param  array{name: string, period: string, grade: int|null, age: int, students: int, homeroom: string}  $classroomDefinition
     */
    private function createExamWithGrades(Classroom $classroom, array $students, array $assessment, array $classroomDefinition, StaffMember $teacher, int $dayOffset): void
    {
        $appliedAt = CarbonImmutable::parse($assessment['week'])->startOfWeek(CarbonInterface::MONDAY)->addDays($dayOffset);
        $plannedOn = $this->latest($appliedAt->subDays($this->randomizer->getInt(5, 12)), CarbonImmutable::parse(self::IMPLANTATION_DATE)->addDays(2));

        if ($plannedOn->greaterThanOrEqualTo($this->referenceDate->startOfDay())) {
            $plannedOn = $this->nextWeekday($this->referenceDate->subDays($this->randomizer->getInt(4, 8)));
        }

        $examCreatedAt = $this->randomTime($plannedOn, 8, 18);

        $exam = new Exam;
        $exam->forceFill([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'title' => $assessment['title'],
            'weight' => $assessment['weight'],
            'applied_at' => $appliedAt->toDateString(),
            'created_at' => $examCreatedAt,
            'updated_at' => $examCreatedAt,
        ])->save();

        $gradingSessionStart = $this->randomTime($this->nextWeekday($appliedAt->addDays($this->randomizer->getInt(1, 5))), 19, 21);

        if ($gradingSessionStart->greaterThan($this->referenceDate)) {
            $this->createBlankGrades($exam, $students, $appliedAt, $examCreatedAt);

            return;
        }

        $examDifficulty = $this->randomizer->getFloat(-0.8, 0.4);
        $enteredAt = $gradingSessionStart;

        foreach ($students as $student) {
            if ($student->created_at->greaterThanOrEqualTo($appliedAt)) {
                continue;
            }

            $enteredAt = $enteredAt->addMinutes($this->randomizer->getInt(1, 4));

            $attributes = $assessment['kind'] === 'graded'
                ? $this->elementaryGradeAttributes($student, $appliedAt, $examDifficulty, $enteredAt)
                : $this->earlyChildhoodRecordAttributes($student, $assessment['kind'], $classroomDefinition['age'], $enteredAt);

            $grade = new Grade;
            $grade->forceFill([
                'student_id' => $student->id,
                'exam_id' => $exam->id,
                ...$attributes,
            ])->save();
        }
    }

    /**
     * Linhas vazias do diário, como o ExamObserver cria ao cadastrar a avaliação: notas ainda não lançadas.
     *
     * @param  list<Student>  $students
     */
    private function createBlankGrades(Exam $exam, array $students, CarbonImmutable $appliedAt, CarbonImmutable $createdAt): void
    {
        foreach ($students as $student) {
            if ($student->created_at->greaterThanOrEqualTo($appliedAt)) {
                continue;
            }

            $grade = new Grade;
            $grade->forceFill([
                'student_id' => $student->id,
                'exam_id' => $exam->id,
                'created_at' => $this->latest($createdAt, CarbonImmutable::parse($student->created_at)),
                'updated_at' => $this->latest($createdAt, CarbonImmutable::parse($student->created_at)),
            ])->save();
        }
    }

    /**
     * @return array{score: float|null, comments: string|null, created_at: CarbonImmutable, updated_at: CarbonImmutable}
     */
    private function elementaryGradeAttributes(Student $student, CarbonImmutable $appliedAt, float $examDifficulty, CarbonImmutable $enteredAt): array
    {
        $score = $this->roundToHalf(
            $this->studentAbilities[$student->id] + $examDifficulty + $this->randomizer->getFloat(-1.3, 1.3)
        );

        if ($this->chance(self::ABSENCE_CHANCE)) {
            $makeUpDate = $this->randomTime($this->nextWeekday($appliedAt->addDays($this->randomizer->getInt(7, 14))), 19, 21);

            if ($makeUpDate->lessThanOrEqualTo($this->referenceDate) && $this->chance(0.6)) {
                return [
                    'score' => $score,
                    'comments' => 'Realizou a segunda chamada em '.$makeUpDate->format('d/m').'.',
                    'created_at' => $enteredAt,
                    'updated_at' => $makeUpDate,
                ];
            }

            return [
                'score' => null,
                'comments' => $this->pick([
                    'Faltou – atestado médico apresentado.',
                    'Faltou sem justificativa.',
                    'Faltou – aguardando segunda chamada.',
                ]),
                'created_at' => $enteredAt,
                'updated_at' => $enteredAt,
            ];
        }

        return [
            'score' => $score,
            'comments' => $this->gradeComment($student, $score),
            'created_at' => $enteredAt,
            'updated_at' => $enteredAt,
        ];
    }

    /**
     * @return array{score: null, comments: string, created_at: CarbonImmutable, updated_at: CarbonImmutable}
     */
    private function earlyChildhoodRecordAttributes(Student $student, string $kind, int $age, CarbonImmutable $enteredAt): array
    {
        $nameParts = explode(' ', $student->name);
        $firstName = in_array($nameParts[0], ['Maria', 'Ana'], true) ? "{$nameParts[0]} {$nameParts[1]}" : $nameParts[0];
        $pools = $age <= 3 ? $this->toddlerObservations() : $this->preschoolObservations();

        $sentences = $kind === 'report'
            ? [
                str_replace('{name}', $firstName, $this->pick($pools['social'])),
                $this->pick($pools['language']),
                $this->pick($pools['motor']),
            ]
            : [
                str_replace('{name}', $firstName, $this->pick($pools['social'])),
                $this->pick([...$pools['language'], ...$pools['motor']]),
            ];

        $enteredAt = $enteredAt->addMinutes($this->randomizer->getInt(3, 8));

        return [
            'score' => null,
            'comments' => implode(' ', $sentences),
            'created_at' => $enteredAt,
            'updated_at' => $enteredAt,
        ];
    }

    /**
     * @return array{social: list<string>, language: list<string>, motor: list<string>}
     */
    private function toddlerObservations(): array
    {
        return [
            'social' => [
                '{name} adaptou-se bem à rotina da turma e chega à escola com segurança.',
                '{name} está aprendendo a compartilhar os brinquedos e já procura os colegas para brincar.',
                '{name} ainda precisa de apoio no momento da despedida da família, mas se acalma rapidamente.',
                '{name} interage com alegria nas brincadeiras de faz de conta.',
                '{name} demonstra carinho com os colegas e participa das atividades coletivas com interesse.',
            ],
            'language' => [
                'Amplia o vocabulário a cada semana e já forma frases curtas para expressar o que deseja.',
                'Gosta de ouvir histórias e aponta as figuras dos livros nomeando objetos e animais.',
                'Comunica-se mais por gestos do que por palavras; seguimos estimulando a fala com cantigas e conversas.',
                'Canta trechos das músicas da rotina e repete rimas com facilidade.',
            ],
            'motor' => [
                'Na hora do lanche, já usa a colher com autonomia.',
                'Está em processo de desfralde, com avanços significativos.',
                'Explora tintas, massinha e diferentes texturas com muita curiosidade.',
                'Sobe e desce do escorregador com segurança e adora as atividades no parque.',
            ],
        ];
    }

    /**
     * @return array{social: list<string>, language: list<string>, motor: list<string>}
     */
    private function preschoolObservations(): array
    {
        return [
            'social' => [
                '{name} participa das rodas de conversa com entusiasmo e respeita a vez dos colegas.',
                '{name} demonstra liderança nas brincadeiras em grupo e ajuda os colegas nas tarefas.',
                '{name} fala pouco nos momentos coletivos, mas se expressa muito bem em pequenos grupos.',
                '{name} está aprendendo a lidar com as frustrações nos jogos e tem mostrado bons progressos.',
                '{name} participa ativamente e traz contribuições interessantes para os projetos da turma.',
            ],
            'language' => [
                'Reconhece e escreve o próprio nome e identifica várias letras do alfabeto.',
                'Faz contagem oral até 20 e relaciona número e quantidade.',
                'Reconta histórias respeitando a sequência dos fatos e inventa finais diferentes.',
                'Já arrisca escritas espontâneas, levantando hipóteses sobre como se escrevem as palavras.',
            ],
            'motor' => [
                'Apresenta boa coordenação motora fina no recorte e na pintura.',
                'Precisa de estímulo na forma de segurar o lápis; seguimos com atividades de massinha e alinhavo.',
                'Demonstra criatividade nos desenhos, com riqueza de detalhes.',
                'Participa com empenho das atividades de movimento e do circuito psicomotor.',
            ],
        ];
    }

    /**
     * Comentário do professor: alunos da Educação Especial costumam ter a avaliação adaptada registrada.
     */
    private function gradeComment(Student $student, float $score): ?string
    {
        if ($student->isSpecialEducation() && $this->chance(0.6)) {
            return $this->pick([
                'Avaliação adaptada, com apoio da professora de AEE.',
                'Realizou a avaliação com tempo estendido e leitura das questões.',
                'Avaliação adaptada conforme o plano de AEE.',
            ]);
        }

        return $this->chance(self::COMMENT_CHANCE) ? $this->teacherComment($score) : null;
    }

    private function teacherComment(float $score): string
    {
        return match (true) {
            $score >= 9.0 => $this->pick([
                'Excelente desempenho!',
                'Parabéns pelo capricho e pela dedicação.',
                'Ótima organização e atenção aos detalhes.',
            ]),
            $score >= 6.0 => $this->pick([
                'Boa evolução em relação à avaliação anterior.',
                'Bom resultado; atenção à letra e à organização.',
                'Pode melhorar a leitura atenta dos enunciados.',
            ]),
            default => $this->pick([
                'Indicado reforço escolar no contraturno.',
                'Conversar com a família sobre a rotina de estudos.',
                'Apresentou dificuldade no conteúdo; retomaremos em sala.',
            ]),
        };
    }

    private function uniqueStudentName(): string
    {
        $firstNames = [
            'Miguel', 'Arthur', 'Gael', 'Théo', 'Heitor', 'Ravi', 'Davi', 'Bernardo', 'Noah', 'Gabriel',
            'Samuel', 'Pedro', 'Benício', 'Lorenzo', 'Matheus', 'Lucas', 'Joaquim', 'Benjamin', 'Isaac', 'Rafael',
            'Enzo', 'Nicolas', 'Lucca', 'Anthony', 'Henrique', 'Murilo', 'Bento', 'Vicente', 'Antônio', 'Caio',
            'Helena', 'Alice', 'Laura', 'Maria Alice', 'Valentina', 'Heloísa', 'Maria Clara', 'Maria Cecília', 'Lorena', 'Cecília',
            'Maitê', 'Liz', 'Sophia', 'Manuela', 'Aurora', 'Isis', 'Antonella', 'Lívia', 'Elisa', 'Júlia',
            'Beatriz', 'Mariana', 'Lara', 'Esther', 'Clara', 'Isabela', 'Ana Luísa', 'Melissa', 'Yasmin', 'Olívia',
        ];

        $surnames = [
            'Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira', 'Alves', 'Pereira', 'Lima', 'Gomes',
            'Costa', 'Ribeiro', 'Martins', 'Carvalho', 'Almeida', 'Lopes', 'Soares', 'Fernandes', 'Vieira', 'Barbosa',
            'Rocha', 'Dias', 'Nascimento', 'Andrade', 'Moreira', 'Nunes', 'Marques', 'Machado', 'Mendes', 'Freitas',
            'Cardoso', 'Ramos', 'Gonçalves', 'Santana', 'Teixeira', 'Araújo', 'Moura', 'Cavalcanti', 'Pinto', 'Campos',
        ];

        do {
            $firstSurname = $this->pick($surnames);
            $name = $this->pick($firstNames).' '.$firstSurname;

            if ($this->chance(0.6)) {
                $name .= ' '.$this->pick(array_values(array_diff($surnames, [$firstSurname])));
            }
        } while (isset($this->usedStudentNames[$name]));

        $this->usedStudentNames[$name] = true;

        return $name;
    }

    private function roundToHalf(float $score): float
    {
        return max(0.0, min(10.0, round($score * 2) / 2));
    }

    private function chance(float $probability): bool
    {
        return $this->randomizer->nextFloat() < $probability;
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[$this->randomizer->getInt(0, count($items) - 1)];
    }

    private function randomTime(CarbonImmutable $date, int $fromHour, int $toHour): CarbonImmutable
    {
        return $date->setTime($this->randomizer->getInt($fromHour, $toHour), $this->randomizer->getInt(0, 59), $this->randomizer->getInt(0, 59));
    }

    private function nextWeekday(CarbonImmutable $date): CarbonImmutable
    {
        while ($date->isWeekend()) {
            $date = $date->addDay();
        }

        return $date;
    }

    private function latest(CarbonImmutable $first, CarbonImmutable $second): CarbonImmutable
    {
        return $first->greaterThan($second) ? $first : $second;
    }
}
