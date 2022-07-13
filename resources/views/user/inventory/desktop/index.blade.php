@extends('layouts.backend')

@section('title', 'Técnico Dashboard')

@section('css')
<link href="{{ asset('/css/datatables/datatable.inventory.pc.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('/js/plugins/datatables/dataTables.bootstrap4.css') }}">

@section('content')
<!-- Page Content -->
<div class="content">
  @include('user.partials.cards')
  <!-- Add Product -->
  <div class="col-md-6 col-xl-2">
    <a class="block block-rounded block-link-shadow" href="{{ route('user.inventory.desktop.create') }}">
      <div class="block-content block-content-full block-sticky-options">
        <div class="block-options">
          <div class="block-options-item">
          </div>
        </div>
        <div class="py-20 text-center">
          <div class="font-size-h2 font-w700 mb-3 text-success">
            <i class="fa fa-plus"></i>
          </div>
          <div class="font-size-sm font-w600 text-uppercase text-muted">Nuevo equipo</div>
        </div>
      </div>
    </a>
  </div>
  <!-- END Add Product -->

  @include('user.partials.modal')

  <div class="col-md-14">
    <div class="block block-rounded block-bordered">
      <div class="block-header block-header-default border-b">
        <h3 class="block-title">
          Equipos informáticos<small> | Lista</small>
        </h3>
        <div class="block-options">
          <button type="button" class="btn-block-option" data-toggle="block-option" data-action="state_toggle"
            data-action-mode="demo">
            <i class="si si-refresh"></i>
          </button>
        </div>
      </div>
      <div class="block-content block-content-full">
        <div class="table-responsive">
          <table id="dt" class="table table-hover" style="width:100%">
            <thead>
              <tr>
                <th></th>
                <th>fecha de creación</th>
                <th>nombre de equipo</th>
                <th>ubicacion</th>
                <th>serial</th>
                <th>activo fijo</th>
                <th>ip</th>
                <th>mac</th>
                <th>
                  <img class="img-fluid" width="80px" src="https://go.anydesk.com/_static/img/logos/anydesk-logo.svg"
                    alt="anydesk">
                </th>
                <th>sede</th>
                <th>estado</th>
                <th>acciones</th>
              </tr>
            </thead>
            <tfoot>
              <tr>
                <th></th>
                <th>FECHA DE CREACIÓN</th>
                <th>nombre de equipo</th>
                <th>ubicacion</th>
                <th>SERIAL</th>
                <th>ACTIVO FIJO</th>
                <th>IP</th>
                <th>MAC</th>
                <th>
                  <img class="img-fluid" width="80px" src="https://go.anydesk.com/_static/img/logos/anydesk-logo.svg"
                    alt="anydesk">
                </th>
                <th>SEDE</th>
                <th>ESTADO</th>
                <th>ACCIONES</th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="block block-rounded block-bordered">
      <div class="block-header block-header-default border-b">
        <h3 class="block-title">
          Equipos informáticos<small> | Eliminados</small>
        </h3>
        <div class="block-options">
          <button type="button" class="btn-block-option" data-toggle="block-option" data-action="state_toggle"
            data-action-mode="demo">
            <i class="si si-refresh"></i>
          </button>
        </div>
      </div>
      <div class="block-content block-content-full">
        <div class="table-responsive">
          <table id="dt-deleted" class="table table-hover">
            <thead>
              <tr>
                <th></th>
                <th>serial</th>
                <th>sede</th>
                <th>estado</th>
                <th>acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach($deletedDevices as $deletedDevice)
              <tr>
                <td></td>
                <td>{{ $deletedDevice->serial_number }}</td>
                <td class="text-center">{{ $deletedDevice->campu }}</td>
                <td><span class="badge badge-pill badge-danger btn-block">
                    {{ $deletedDevice->status }}</span>
                </td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-secondary"
                    data-id="{{ $deletedDevice->id }}" id="btn-restore">
                    <i class="fa fa-undo"></i>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('js')
<script src="{{ asset('/js/datatables/datatable.inventory.pc.js') }}"></script>
<script src="{{ asset('/js/pages/be_tables_datatables.min.js') }}"></script>
<script src="{{ asset('/js/plugins/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('/js/plugins/datatables/dataTables.bootstrap4.min.js') }}"></script>

@if(Session::has('pc_created'))
<script>
  Swal.fire(
'Creado con Exito!',
'{!! Session::get('pc_created') !!}',
'success'
)
</script>
@endif

@if(Session::has('info_error'))
<script>
  Swal.fire(
'Ha Ocurrido Un Error Al Crear El Equipo!',
'{!! Session::get('info_error') !!}',
'warning'
)
</script>
@endif

@if(Session::has('pc_updated'))
<script>
  Swal.fire(
'Actualizado con Exito!',
'{!! Session::get('pc_updated') !!}',
'success'
)
</script>
@endif

<script>
  let root_url_desktop = <?php echo json_encode(route('user.inventory.desktop.index')) ?>;
  let root_url_desktop_store = <?php echo json_encode(route('user.inventory.desktop.store')) ?>;
  let root_url_restore = <?php echo json_encode(route('user.inventory.restore.update')) ?>;
</script>

<script>
  $(document).ready(function() {
  
  $.ajaxSetup({
  headers: {
  "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
  }
  });
  
  $(document).on("click", "#btn-restore", function(e) {
  console.log(e);
  Swal.fire({
  title: "Estas seguro?",
  text: "No se podra revertir esto!",
  icon: "warning",
  showCancelButton: true,
  confirmButtonColor: "#3085d6",
  cancelButtonColor: "#d33",
  confirmButtonText: "Si, borrar!",
  cancelButtonText: "No, cancelar"
  }).then(result => {
  if (result.isConfirmed) {
  event.preventDefault();
  let id = $(this).attr("data-id");
  //console.log(id);
  $.ajax({
  url: root_url_restore + "/" + id,
  type: "PUT",
  data: {
  _token: $('input[name="_token"]').val()
  },
      success: function(response) {
      console.log(response);
        Swal.fire();
          let table = $("#dt-restore");
            window.location.reload();
              console.log(id);
      }
  });
  }
  });
  return false;
  });
  
  });
</script>
@endpush