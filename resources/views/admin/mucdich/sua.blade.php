@extends('admin.layouts.index')
@section('content')
<!-- Page Content -->
<script>
    function validateForm() {
        var x = document.forms["suaMucDich"]["ten"].value.trim();
        if (x == "") {
            alert("Bạn chưa nhập tên mục đích");
            return false;
        } else {
            return true;
        }
    }
</script>
<div id="page-wrapper">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-12">
                        <h1 class="page-header">Mục Đích
                            <small>Sửa</small>
                        </h1>
                    </div>
                     @if(count($errors) > 0)
                    <div class="col-lg-12">
                        <div class="alert alert-danger">
                            @foreach($errors -> all() as $err)
                            {{$err}}<br>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    <!-- /.col-lg-12 -->
                    <div class="col-lg-7" style="padding-bottom:120px">
                        <form name="suaMucDich" action="{{route('suaMucDich',$mucdich->id)}}" method="POST" onsubmit="return validateForm()" enctype="multipart/form-data">
                            <input type="hidden" name="_token" value="{{csrf_token()}}">
                                <div class="form-group">
                                    <label>Tên</label>
                                    <input class="form-control" name="ten" required value="{{$mucdich->ten}}" placeholder="nhập tên mục đích"/>
                                </div>
                              <div class="form-group">
                                  <label>Chọn ảnh đại diện</label>
                                  <input value="{{$mucdich->anh}}"  type="file" name="anh">
                             </div>
                             <div class="form-group">
                                <td><img src="{{$mucdich->anh}}" alt="" width="150px" height="150px"></td>
                             </div>
                           <div class="form-group" >
                                <div class="col-md-4 col-md-offset-3 container-fluid">
                                    <button type="submit" class="btn btn-primary pull-left">Lưu</button>
                                    <button type="button" class="btn btn-warning pull-right" onclick="window.location='{{ URL::previous() }}'">Huỷ bỏ</button>
                                </div>
                            </div>
                        <form>
                    </div>
                </div>
                <!-- /.row -->
            </div>
            <!-- /.container-fluid -->
</div>
@endsection