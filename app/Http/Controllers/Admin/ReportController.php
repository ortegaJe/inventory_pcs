<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Helpers\Helper;
use App\Models\Device;
use App\Models\DeviceMaintenance;
use App\Models\Report;
use App\Models\ReportResume;
use Faker\Provider\Uuid;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    private $generatorID;

    public function __construct()
    {
        $this->generatorID = Helper::IDGenerator(new Report, 'report_code_number', 12, 'REPO');
        $this->report = new Report();
        $this->report_resume = new ReportResume();
        $this->report_maintenance = new DeviceMaintenance();
    }

    public function getReport()
    {
        return view('report.index');
    }

    public function indexReportRemove(Request $request)
    {
        $user_id = Auth::id();

        $serial_number = $request->get('search');

        $devices = Device::leftJoin('campus as c', 'c.id', 'devices.campu_id')
            ->leftJoin('campu_users as cu', 'cu.campu_id', 'devices.campu_id')
            ->leftJoin('users as u', 'u.id', 'cu.user_id')
            ->leftJoin('status as s', 's.id', 'devices.statu_id')
            ->select(
                'devices.inventory_code_number',
                'devices.serial_number',
                'devices.ip',
                'devices.mac',
                'cu.campu_id',
                'c.name as sede',
                's.name as estado',
                's.id as statu_id',
                'devices.rowguid',
                'devices.id as device_id'
            )
            ->where('cu.user_id', $user_id)
            ->where('devices.is_active', true)
            ->whereIn('devices.statu_id', [1, 2, 3, 5, 6, 7, 8])
            ->search($serial_number)
            ->orderByDesc('devices.created_at')
            ->paginate(10);

        //return response()->json($devices);

        return view('report.removes.create', compact('devices'));
    }

    public function createReportRemove($id, $uuid)
    {
        $user_id = Auth::id();

        $device = Device::findOrFail($id);

        $technician_solutions = DB::table('technician_solutions')
            ->select('id', 'name')
            ->get();

        $report_removes = DB::table('reports as r')
            ->leftJoin('report_names as rn', 'rn.id', 'r.report_name_id')
            ->leftJoin('report_removes as rr', 'rr.report_id', 'r.id')
            ->leftJoin('devices as d', 'd.id', 'r.device_id')
            ->select(
                'report_code_number',
                'r.id as repo_id',
                'r.rowguid',
                DB::raw("UPPER(rn.name) repo_name"),
                'd.serial_number as serial_number',
                DB::raw("DATE_FORMAT(r.created_at, '%c/%e/%Y - %r') date_created")
            )
            ->where('r.user_id', $user_id)
            ->where('r.device_id', $device->id)
            ->orderByDesc('r.created_at')
            ->get();

        //return response()->json($report_removes);

        $data = [
            'device' => $device,
            'technician_solutions' => $technician_solutions,
            'report_removes' => $report_removes
        ];

        return view('report.removes.show')->with($data);
    }

    public function storeReportRemove(Request $request)
    {
        $user_id = Auth::id();
        $device_id = e($request->input('device-id'));
        $tec_solutions = e($request->input('val-select2-tec-solutions'));
        $diagnostic = e($request->input('diagnostic'));
        $observation = e($request->input('observation'));

        $rules = [
            'val-select2-tec-solutions' => [
                'required',
                'numeric',
                Rule::in([1, 2, 3, 4, 5, 6])
            ],

            'diagnostic' => 'required',
            'observation' => 'nullable'
        ];

        $messages = [
            'val-select2-tec-solutions.required' => 'Es requerido una solucion técnica',
            'val-select2-tec-solutions.in' => 'Seleccione una opción valida en la lista',
            'diagnostic.required' => 'Es requerido un diagnóstico',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) :
            return back()->withErrors($validator)
                ->withInput()
                ->with(
                    'message',
                    'Revisar campos! :-('
                )->with(
                    'modal',
                    'error'
                );
        else :
            DB::beginTransaction();

            DB::insert(
                "CALL SP_insertReport (?,?,?,?,?,?,?,?,?)",
                [
                    $this->report->report_code_number = $this->generatorID,
                    $this->report->report_name_id = Report::REPORT_REMOVE_NAME_ID,
                    $this->report->device_id = $device_id,
                    $this->report->user_id = $user_id,
                    $this->report->rowguid = Uuid::uuid(),
                    $this->report->created_at = now('America/Bogota'),

                    $this->report_remove->technician_solution_id = $tec_solutions,
                    $this->report_remove->diagnostic = $diagnostic,
                    $this->report_remove->observation = $observation,
                ]
            );
            DB::commit();
            return back()->withErrors($validator)
                ->with('report_created', 'Reporte ' . $this->report->report_code_number . '');
            try {
            } catch (\Throwable $e) {
                DB::rollback();
                return back()->with('info_error', '');
                throw $e;
            }
        endif;
    }

    public function reportRemoveGenerated($id)
    {
        $report = Report::findOrFail($id);

        $generated_report_remove = DB::table('view_report_removes')
            ->where('RepoID', $id)
            ->get();

        //return response()->json($generated_report_remove);

        $pdf = PDF::loadView(
            'report.removes.pdf',
            [
                'generated_report_remove' => $generated_report_remove
            ]
        );

        return $pdf->stream('formato_de_solicitud_de_baja_' . $report->report_code_number . '.pdf');
    }

    public function indexReportResume(Request $request)
    {
        $user_id = Auth::id();

        $serial_number = $request->get('search');

        $devices = Device::leftJoin('campus as c', 'c.id', 'devices.campu_id')
            ->leftJoin('campu_users as cu', 'cu.campu_id', 'devices.campu_id')
            ->leftJoin('users as u', 'u.id', 'cu.user_id')
            ->leftJoin('status as s', 's.id', 'devices.statu_id')
            ->select(
                'devices.inventory_code_number',
                'devices.serial_number',
                'devices.ip',
                'devices.mac',
                'cu.campu_id',
                'c.name as sede',
                's.name as estado',
                's.id as statu_id',
                'devices.rowguid',
                'devices.id as device_id'
            )
            ->where('cu.user_id', $user_id)
            ->where('devices.is_active', true)
            ->whereIn('devices.statu_id', [1, 2, 3, 5, 6, 7, 8])
            ->search($serial_number)
            ->orderByDesc('devices.created_at')
            ->paginate(10);

        //return response()->json($devices);

        return view('report.resumes.create', compact('devices'));
    }

    public function createReportResume($id, $uuid)
    {
        $user_id = Auth::id();

        $device = Device::findOrFail($id);

        $report_resume_count = Report::select('device_id')
            ->where('device_id', $device->id)
            ->count();

        $report_maintenance_count = Report::select('device_id')
            ->where('report_name_id', Report::REPORT_RESUME_NAME_ID)
            ->where('device_id', $device->id)
            ->count();

        $report_resumes = DB::table('reports as r')
            ->leftJoin('report_names as rn', 'rn.id', 'r.report_name_id')
            ->leftJoin('devices as d', 'd.id', 'r.device_id')
            ->select(
                'r.report_code_number',
                'r.id as repo_id',
                'r.rowguid',
                DB::raw("UPPER(rn.name) repo_name"),
                'd.serial_number as serial_number',
                DB::raw("DATE_FORMAT(r.created_at, '%c/%e/%Y - %r') date_created")
            )
            ->where('r.user_id', $user_id)
            ->where('r.device_id', $device->id)
            ->where('r.report_name_id', Report::REPORT_RESUME_NAME_ID)
            ->orderByDesc('r.created_at')
            ->get();

        //return response()->json($report_resumes);

        $report_maintenances = DB::table('reports as r')
            ->leftJoin('device_maintenances as dm', 'dm.report_id', 'r.id')
            ->leftJoin('devices as d', 'd.id', 'r.device_id')
            ->select(
                'r.id as repo_id',
                'dm.report_id as repo_maintenace_id',
                'd.serial_number as serial_number',
                'dm.observation',
                DB::raw("DATE_FORMAT(dm.maintenance_date, '%c/%e/%Y - %r') maintenance_date")
                //'r.rowguid'
            )
            //->where('r.id', '=', 'dm.report_id')
            ->where('r.user_id', $user_id)
            ->where('r.device_id', $device->id)
            ->where('r.report_name_id', Report::REPORT_RESUME_NAME_ID)
            ->latest('dm.maintenance_date')
            ->limit(1)
            ->get();

        //return response()->json($report_maintenances);

        $data = [
            'device' => $device,
            'report_resume_count' => $report_resume_count,
            'report_maintenance_count' => $report_maintenance_count,
            'report_resumes' => $report_resumes,
            'report_maintenances' => $report_maintenances
        ];

        return view('report.resumes.show')->with($data);
    }

    public function storeReportResume(Request $request)
    {
        $user_id = Auth::id();
        $device_id = e($request->input('device-id'));

        $rules = [];

        $messages = [];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) :
            return back()->withErrors($validator)
                ->withInput()
                ->with(
                    'message',
                    'Revisar campos! :-('
                )->with(
                    'modal',
                    'error'
                );
        else :
            DB::beginTransaction();

            DB::insert(
                "CALL SP_insertReportResume (?,?,?,?,?,?)",
                [
                    $this->report->report_code_number = $this->generatorID,
                    $this->report->report_name_id = Report::REPORT_RESUME_NAME_ID,
                    $this->report->device_id = $device_id,
                    $this->report->user_id = $user_id,
                    $this->report->rowguid = Uuid::uuid(),
                    $this->report->created_at = now('America/Bogota'),
                ]
            );
            DB::commit();
            return back()->withErrors($validator)
                ->with('report_created', 'Reporte ' . $this->report->report_code_number . '');
            try {
            } catch (\Throwable $e) {
                DB::rollback();
                return back()->with('info_error', '');
                throw $e;
            }
        endif;
    }

    public function reportResumeGenerated($id)
    {
        $report = Report::findOrFail($id);

        $generated_report_resume = DB::table('view_report_resumes')
            ->where('RepoID', $report->id)
            ->get();

        //return response()->json($generated_report_resume);

        $mto_count = DeviceMaintenance::select('report_id')->get();

        $maintenance_date = DeviceMaintenance::leftJoin('reports as r', 'r.id', 'device_maintenances.report_id')
            ->select(
                'device_maintenances.report_id as mto_repo_id',
                'r.id as repo_id',
                'r.report_code_number',
                'device_maintenances.maintenance_date as mto_date',
                'device_maintenances.observation'
            )
            ->where('device_maintenances.report_id', $report->id)
            ->limit(2)
            ->get();

        //return response()->json($maintenance_date);

        $pdf = PDF::loadView(
            'report.resumes.pdf.cv-pdf',
            [
                'report' => $report,
                'mto_count' => $mto_count,
                'generated_report_resume' => $generated_report_resume,
                'maintenance_date' => $maintenance_date
            ]
        );

        $nombre_carpeta = $report->report_code_number;

        Storage::put('public/' . $report->report_code_number . '.pdf', $pdf->output());

        return $pdf->download($report->report_code_number . '.pdf');
    }

    public function storeReportMaintenance(Request $request)
    {
        $report_id = e($request->input('repo-id'));
        $maintenance_date = e($request->input('maintenance-date'));
        $observation = e($request->input('observation'));

        $rules = [];

        $messages = [];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) :
            return back()->withErrors($validator)
                ->withInput()
                ->with(
                    'message',
                    'Revisar campos! :-('
                )->with(
                    'modal',
                    'error'
                );
        else :

            $this->report_maintenance->report_id = $report_id;
            //$this->report->rowguid = Uuid::uuid();
            $this->report_maintenance->maintenance_date = $maintenance_date;
            $this->report_maintenance->observation = $observation;

            $this->report_maintenance->save();

            return back()->withErrors($validator)
                ->with('report_created', '');

        endif;
    }

    public function pdfReportMaintenance(Request $request, $id)
    {
        //$exists = Storage::disk('public')->exists("REPO000000000018.pdf");
        //return response()->json($exists);

        $report = Report::findOrFail($id);
        //return response()->json($report);

        $nombre_carpeta = $report->report_code_number;
        //return response()->json($nombre_carpeta);

        /*if (Storage::disk('public')->exists("/$request->file")) {
            $path = Storage::disk('public')->path("/$request->file");
            $content = file_get_contents($path);
            return response($content)->withHeaders([
                'Content-Type' => mime_content_type($path)
            ]);
        }
        return redirect('/404');*/

        //dd($report->id);
        //eturn response()->download('./storage/app/public/' . $report->report_code_number . '.pdf');
        if (Storage::disk('public')->exists($report->report_code_number . '.pdf')) {
            return Storage::download('public/' . $report->report_code_number . '.pdf');
        }
        return redirect('/404');
    }
}
