<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CampusExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCampuRequest;
use App\Models\Campu;
use App\Models\CampuUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Maatwebsite\Excel\Excel;
use Illuminate\Support\Str;

use function PHPUnit\Framework\isNull;

class CampuController extends Controller
{
    private $excel;

    public function __construct(Excel $excel)
    {
        $this->excel = $excel;
    }

    public function index(Request $request)
    {
        $name = $request->get('search');

        $campus = Campu::orderBy('id', 'desc')
            ->name($name)
            ->paginate(6);

        return view('admin.sedes.index', compact('campus'));
    }

    public function autoCompleteSearch(Request $request)
    {
        $query = $request->get('search');

        $filterResult = Campu::where('name', 'LIKE', '%' . $query . '%')->get();

        return response()->json($filterResult);
    }

    public function exportCampu($campuId, $campuSlug)
    {
        $rand = Str::upper(Str::random(12));

        //$exceptColumn = $campuId->except('CampuID');

        return $this->excel->download(
            new CampusExport($campuId),
            "export_inventory_" . Str::lower($campuSlug) . "_devices_" . $rand . ".xlsx"
        );
    }

    public function assingUserCampu(Request $request, $id)
    {

        $userId = $request->get('val-select2-lista-tecnicos');

        //busca sede donde el valor del request coincida con el resultado de la variable $userId
        $foundCampuId = DB::table('campu_users')->select('campu_id', 'user_id', 'is_principal')
            ->where('campu_id', $id)
            ->first();

        if (isset($foundCampuId) === false) {
            //inserta una nueva sede con usuario asignado
            DB::table('campu_users')->insert([
                'user_id'    => $userId,
                'campu_id'   => $id,
                'created_at' => now('America/Bogota')
            ]);

            return back()->with('assigned', '');
        } else
            //actualiza sede con nuevo usuario asignado
            $update = array(
                'user_id'      => $userId,
                'is_principal' => false,
                'updated_at'   => now('America/Bogota')
            );
        DB::table('campu_users')->where('campu_id', $id)->update($update);

        return back()->with('assigned', '');
    }

    public function removeUserCampu($id)
    {
        $campu = null;
        $userCampuRemoved = [];
        $campu = Campu::findOrFail($id);
        $ts = now('America/Bogota')->toDateTimeString();
        error_log(__LINE__ . __METHOD__ . ' pc --->' . $id);
        try {

            $userCampuRemoved[] = DB::table('campus as c')
                ->select(
                    'c.id as SedeID',
                    'c.name as NombreSede',
                    'u.id as UserID',
                    DB::raw("CONCAT(UPPER(u.name),' ',UPPER(u.last_name)) as NombreCompletoTecnico")
                )
                ->leftJoin('campu_users as cu', 'cu.campu_id', 'c.id')
                ->leftJoin('users as u', 'u.id', 'cu.user_id')
                ->where('cu.campu_id', $id)
                ->get();

            $update = array('user_id' => null, 'is_principal' => false, 'updated_at' => $ts);
            $campuToRemoved = DB::table('campu_users')->where('campu_id', $id)->update($update);

            error_log(__LINE__ . __METHOD__ . ' pc --->' . var_export($campuToRemoved, true));
        } catch (ModelNotFoundException $e) {
            // Handle the error.
        }

        return response()->json([
            'message' => 'Ya no se encuentra asignado a la sede ' . $campu->name . '',
            'result' => $userCampuRemoved[0]
        ]);
    }

    public function create()
    {
        return view('admin.sedes.create');
    }


    public function store(Request $request)
    {
        $campu = new Campu();

        $request->validate([
            'abreviature' => 'required|unique:campus,abreviature',
            'name' => 'required|unique:campus,name',
        ]);

        $campu->abreviature = e($request->input('abreviature'));
        $campu->name = e($request->input('name'));
        $campu->slug = e($request->input('slug'));
        $campu->address = e($request->input('address'));
        $campu->phone = e($request->input('phone'));
        $campu->created_at = now('America/Bogota');

        $campu->save();

        return redirect()->route('admin.inventory.campus.index', $campu)
            ->with('info', 'Sede ' . $campu->name . ' creada exitosamente!');
    }

    public function show($id)
    {
        $campus = Campu::findOrFail($id);

        $userLists = User::all();

        $typeDevices = DB::table('devices as d')
            ->leftJoin('type_devices as td', 'td.id', 'd.type_device_id')
            ->leftJoin('components as c', 'c.device_id', 'd.id')
            ->select(
                DB::raw("COUNT(d.type_device_id) AS numberTypeDevice"),
                'td.name as nameTypeDevice',
                'd.campu_id as SedeId'
            )
            ->where('d.campu_id', $id)
            ->groupBy('d.type_device_id', 'td.name', 'd.campu_id')
            ->get();
        //return response()->json($typeDevices);

        $campusCount = DB::table('devices')
            ->select('campu_id')
            ->where('campu_id', $id)
            ->count();

        $getIdUserByCampus = DB::table('campu_users')
            ->select('user_id')
            ->where('campu_id', $id)
            ->first();

        $campuAssignedCount = DB::table('campu_users')
            ->select(DB::raw("campu_id,user_id,COUNT(user_id) AS NumberCampus"))
            ->where('campu_id', $id)
            ->orWhere('user_id', ($getIdUserByCampus) ? $getIdUserByCampus->user_id : 0)
            ->count();

        //dd($campuAssignedCount);

        $campuAssigned = DB::table('campus AS C')
            ->select(
                'C.id AS SedeID',
                'U.id AS UserID',
                'C.name AS NombreSede',
                DB::raw("CONCAT(U.name,' ',
                U.last_name) AS NombreCompletoTecnico"),
                'P.name AS CargoTecnico',
                'U.email AS EmailTecnico'
            )
            ->leftJoin('campu_users AS CU', 'CU.campu_id', 'C.id')
            ->leftJoin('users AS U', 'U.id', 'CU.user_id')
            ->join('profile_users AS PU', 'PU.user_id', 'U.id')
            ->join('profiles AS P', 'P.id', 'PU.profile_id')
            ->where('CU.campu_id', $id)
            ->where('U.is_active', 1)
            ->get();

        //return $campuAssigned;

        $data =
            [
                'campus' => $campus,
                'typeDevices' => $typeDevices,
                'campusCount' => $campusCount,
                'campuAssigned' => $campuAssigned,
                'campuAssignedCount' => $campuAssignedCount,
                'userLists' => $userLists,
            ];

        return view('admin.sedes.show')->with($data);
    }

    public function edit($id)
    {
        return view('admin.sedes.edit', compact('campu'));
    }

    public function update(Request $request, $id)
    {
        $campu = Campu::findOrFail($id);

        $this->validate(
            request(),
            ['abreviature' => ['required', 'max:4', 'unique:campus,abreviature,' . $id]],
            ['name' => ['required', 'unique:campus,name,' . $id]],
            //['address' => ['required', 'unique:campus,address,' . $id]],
            //['phone' => ['required', 'unique:campus,phone,' . $id]],
            ['slug' => ['required', 'unique:campus,slug,' . $id]]
        );

        $campu->abreviature = $request->get('abreviature');
        $campu->name = $request->get('name');
        $campu->address = $request->get('address');
        $campu->phone = $request->get('phone');
        $campu->slug = $request->get('slug');
        $campu->updated_at = now('America/Bogota');

        $campu->save();


        return back()->with('info', 'Sede ' . $campu->name . ' actualizada con exito!');
    }

    public function destroy($id)
    {
        //
    }
}
