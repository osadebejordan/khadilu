@extends('layouts.app', ['title' => 'Product Attributes'])

@section('content')
<div class="grid-2-col" style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
  <!-- Categories Card -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Product Categories</h3>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('attributes.category.store') }}" style="display:flex; gap:12px; margin-bottom:20px;">
        @csrf
        <input type="text" name="name" class="form-control" placeholder="New Category Name..." required>
        <button type="submit" class="btn btn-primary" style="white-space:nowrap;">+ Add Category</button>
      </form>

      <ul style="list-style:none; padding:0; margin:0;">
        @foreach($categories as $category)
        <li style="padding:12px 0; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
          <span style="font-weight:600;">{{ $category->name }}</span>
          <form action="{{ route('attributes.category.destroy', $category->id) }}" method="POST" onsubmit="return confirm('Delete category?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
          </form>
        </li>
        @endforeach
      </ul>
    </div>
  </div>

  <!-- Qualities Card -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Quality Grades</h3>
    </div>
    <div class="card-body">
      <form method="POST" action="{{ route('attributes.quality.store') }}" style="display:flex; gap:12px; margin-bottom:20px;">
        @csrf
        <input type="text" name="name" class="form-control" placeholder="e.g. Original, Generic..." required>
        <button type="submit" class="btn btn-primary" style="white-space:nowrap;">+ Add Quality</button>
      </form>

      <ul style="list-style:none; padding:0; margin:0;">
        @foreach($qualities as $quality)
        <li style="padding:12px 0; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
          <span style="font-weight:600;">{{ $quality->name }}</span>
          <form action="{{ route('attributes.quality.destroy', $quality->id) }}" method="POST" onsubmit="return confirm('Delete quality grade?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
          </form>
        </li>
        @endforeach
      </ul>
    </div>
  </div>
</div>
@endsection
