use App\Models\Employee;
use App\Models\DefaultDimension;
use App\Models\DimensionValue;
use App\Models\Dimension;
use App\Enums\EmployeeAssignmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\OrgDimensionSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(OrgDimensionSeeder::class);
});

test('factory employee creation syncs all dimensions', function () {
    $employee = Employee::create([
        'employee_number' => 'FAC001',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'assignment_type' => EmployeeAssignmentType::Factory,
        'business_code' => 'NORTH',
        'factory_code' => 'ALPHA',
        'department_code' => 'PROD',
    ]);

    expect(DefaultDimension::where([
        'table_id' => '5200',
        'no' => 'FAC001',
        'dimension_code' => 'BUSINESS',
        'dimension_value_code' => 'NORTH',
    ])->exists())->toBeTrue();

    expect(DefaultDimension::where([
        'table_id' => '5200',
        'no' => 'FAC001',
        'dimension_code' => 'FACTORY',
        'dimension_value_code' => 'ALPHA',
    ])->exists())->toBeTrue();

    expect(DefaultDimension::where([
        'table_id' => '5200',
        'no' => 'FAC001',
        'dimension_code' => 'DEPARTMENT',
        'dimension_value_code' => 'PROD',
    ])->exists())->toBeTrue();
});

test('corporate employee creation only syncs department dimension', function () {
    $employee = Employee::create([
        'employee_number' => 'CORP001',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'assignment_type' => EmployeeAssignmentType::Corporate,
        'department_code' => 'HR',
        'business_code' => 'NORTH', // Should be ignored/cleared
    ]);

    expect($employee->business_code)->toBeNull();

    expect(DefaultDimension::where([
        'table_id' => '5200',
        'no' => 'CORP001',
        'dimension_code' => 'DEPARTMENT',
        'dimension_value_code' => 'HR',
    ])->exists())->toBeTrue();

    expect(DefaultDimension::where([
        'table_id' => '5200',
        'no' => 'CORP001',
        'dimension_code' => 'BUSINESS',
    ])->exists())->toBeFalse();
});

test('switching from factory to corporate clears dimensions', function () {
    $employee = Employee::create([
        'employee_number' => 'X001',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'assignment_type' => EmployeeAssignmentType::Factory,
        'business_code' => 'NORTH',
        'factory_code' => 'ALPHA',
        'department_code' => 'PROD',
    ]);

    $employee->update(['assignment_type' => EmployeeAssignmentType::Corporate]);

    expect($employee->business_code)->toBeNull();
    expect($employee->factory_code)->toBeNull();

    expect(DefaultDimension::where([
        'table_id' => '5200',
        'no' => 'X001',
        'dimension_code' => 'BUSINESS',
    ])->exists())->toBeFalse();
});
