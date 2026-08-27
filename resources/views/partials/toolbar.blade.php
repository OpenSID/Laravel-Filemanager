<div class="navbar navbar-fixed-top">
    <div class="navbar-inner">
        <div class="container-fluid">
            <button type="button" class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <div class="brand">{{ __('filemanager::filemanager.Toolbar') }}</div>
            <div class="nav-collapse collapse">
                <div class="filters">
                    <div class="row-fluid">
                        <div class="span4 half">
                            @if ($canUpload)
                                <button class="tip btn upload-btn" title="{{ __('filemanager::filemanager.Upload_file') }}"><i class="rficon-upload"></i></button>

                                @if (config('filemanager.text_editing_enabled'))
                                    <button class="tip btn create-file-btn" title="{{ __('filemanager::filemanager.New_File') }}"><i class="icon-plus"></i><i class="icon-file"></i></button>
                                @endif

                                <button class="tip btn new-folder" title="{{ __('filemanager::filemanager.New_Folder') }}"><i class="icon-plus"></i><i class="icon-folder-open"></i></button>
                            @endif

                            @if ($canDelete)
                                <button class="tip btn paste-here-btn" title="{{ __('filemanager::filemanager.Paste_Here') }}"><i class="rficon-clipboard-apply"></i></button>
                                <button class="tip btn clear-clipboard-btn" title="{{ __('filemanager::filemanager.Clear_Clipboard') }}"><i class="rficon-clipboard-clear"></i></button>
                            @endif

                            <div id="multiple-selection" style="display:none;">
                                @if ($canDelete)
                                    <button class="tip btn multiple-delete-btn" title="{{ __('filemanager::filemanager.Erase') }}" data-confirm="{{ __('filemanager::filemanager.Confirm_del') }}"><i class="icon-trash"></i></button>
                                @endif
                                <button class="tip btn multiple-select-btn" title="{{ __('filemanager::filemanager.Select_All') }}"><i class="icon-check"></i></button>
                                <button class="tip btn multiple-deselect-btn" title="{{ __('filemanager::filemanager.Deselect_All') }}"><i class="icon-ban-circle"></i></button>
                            </div>
                        </div>

                        <div class="span2 half view-controller">
                            <button class="btn tip{{ $viewType == 0 ? ' btn-inverse' : '' }}" id="view0" data-value="0" title="{{ __('filemanager::filemanager.View_boxes') }}"><i class="icon-th {{ $viewType == 0 ? 'icon-white' : '' }}"></i></button>
                            <button class="btn tip{{ $viewType == 1 ? ' btn-inverse' : '' }}" id="view1" data-value="1" title="{{ __('filemanager::filemanager.View_list') }}"><i class="icon-align-justify {{ $viewType == 1 ? 'icon-white' : '' }}"></i></button>
                            <button class="btn tip{{ $viewType == 2 ? ' btn-inverse' : '' }}" id="view2" data-value="2" title="{{ __('filemanager::filemanager.View_columns_list') }}"><i class="icon-fire {{ $viewType == 2 ? 'icon-white' : '' }}"></i></button>
                        </div>

                        <div class="span6 entire types">
                            <span>{{ __('filemanager::filemanager.Filters') }}:</span>
                            @if ($type != 1 && $type != 3 && $type != 4 && $type != 5)
                                <input type="radio" id="ff-item-type-all" name="radio-sort" data-item="ff-item-type-all" class="hide" checked />
                                <label id="ff-item-type-all-lbl" class="btn btn-inverse" for="ff-item-type-all" title="{{ __('filemanager::filemanager.All') }}"><i class="icon-align-justify icon-white"></i></label>

                                <input type="radio" id="ff-item-type-1" name="radio-sort" data-item="ff-item-type-1" class="hide" />
                                <label id="ff-item-type-1-lbl" class="btn" for="ff-item-type-1" title="{{ __('filemanager::filemanager.Files') }}"><i class="icon-file"></i></label>

                                <input type="radio" id="ff-item-type-2" name="radio-sort" data-item="ff-item-type-2" class="hide" />
                                <label id="ff-item-type-2-lbl" class="btn" for="ff-item-type-2" title="{{ __('filemanager::filemanager.Images') }}"><i class="icon-picture"></i></label>

                                <input type="radio" id="ff-item-type-3" name="radio-sort" data-item="ff-item-type-3" class="hide" />
                                <label id="ff-item-type-3-lbl" class="btn" for="ff-item-type-3" title="{{ __('filemanager::filemanager.Archives') }}"><i class="icon-inbox"></i></label>

                                <input type="radio" id="ff-item-type-4" name="radio-sort" data-item="ff-item-type-4" class="hide" />
                                <label id="ff-item-type-4-lbl" class="btn" for="ff-item-type-4" title="{{ __('filemanager::filemanager.Videos') }}"><i class="icon-film"></i></label>

                                <input type="radio" id="ff-item-type-5" name="radio-sort" data-item="ff-item-type-5" class="hide" />
                                <label id="ff-item-type-5-lbl" class="btn" for="ff-item-type-5" title="{{ __('filemanager::filemanager.Music') }}"><i class="icon-music"></i></label>
                            @endif
                            <input accesskey="f" type="text" class="filter-input" id="filter-input" name="filter" placeholder="{{ mb_strtolower(__('filemanager::filemanager.Text_filter')) }}..." value="{{ $filter }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row-fluid">
    <ul class="breadcrumb">
        <li class="pull-left"><a href="?{{ http_build_query($linkParams + ['fldr' => trim((string) config('filemanager.base_folder', ''), '/')]) }}"><i class="icon-home"></i></a></li>
        <li><span class="divider">/</span></li>
        @foreach ($breadcrumbs as $crumb)
            @if (! $loop->last)
                <li><a href="?{{ http_build_query($linkParams + ['fldr' => $crumb['path']]) }}">{{ $crumb['name'] }}</a></li>
                <li><span class="divider">/</span></li>
            @else
                <li class="active">{{ $crumb['name'] }}</li>
            @endif
        @endforeach

        <li class="pull-right"><a class="btn-small" href="javascript:void('')" id="info"><i class="icon-question-sign"></i></a></li>
        <li class="pull-right"><a id="refresh" class="btn-small" href="?{{ http_build_query($linkParams + ['fldr' => $subdir]) }}"><i class="icon-refresh"></i></a></li>

        <li class="pull-right">
            <div class="btn-group">
                <a class="btn dropdown-toggle sorting-btn" data-toggle="dropdown" href="javascript:void('')">
                    <i class="icon-signal"></i>
                    <span class="caret"></span>
                </a>
                <ul class="dropdown-menu pull-right sorting">
                    <li class="text-center"><strong>{{ __('filemanager::filemanager.Sorting') }}</strong></li>
                    <li><a class="sorter sort-name {{ $sortBy == 'name' ? ($descending ? 'descending' : 'ascending') : '' }}" href="javascript:void('')" data-sort="name">{{ __('filemanager::filemanager.Filename') }}</a></li>
                    <li><a class="sorter sort-date {{ $sortBy == 'date' ? ($descending ? 'descending' : 'ascending') : '' }}" href="javascript:void('')" data-sort="date">{{ __('filemanager::filemanager.Date') }}</a></li>
                    <li><a class="sorter sort-size {{ $sortBy == 'size' ? ($descending ? 'descending' : 'ascending') : '' }}" href="javascript:void('')" data-sort="size">{{ __('filemanager::filemanager.Size') }}</a></li>
                    <li><a class="sorter sort-extension {{ $sortBy == 'extension' ? ($descending ? 'descending' : 'ascending') : '' }}" href="javascript:void('')" data-sort="extension">{{ __('filemanager::filemanager.Type') }}</a></li>
                </ul>
            </div>
        </li>
        <li>
            <small class="hidden-phone">
                (<span id="files_number">{{ collect($entries)->where('is_dir', false)->count() }}</span> {{ __('filemanager::filemanager.Files') }}
                - <span id="folders_number">{{ collect($entries)->where('is_dir', true)->count() }}</span> {{ __('filemanager::filemanager.Folders') }})
            </small>
        </li>
    </ul>
</div>
