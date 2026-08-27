<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('filemanager::filemanager.Toolbar') }}</title>
    <link rel="shortcut icon" href="{{ filemanager_asset('img/ico/favicon.ico') }}">

    <link rel="stylesheet" href="{{ filemanager_asset('css/jquery.fileupload.css') }}">
    <link rel="stylesheet" href="{{ filemanager_asset('css/jquery.fileupload-ui.css') }}">
    <noscript><link rel="stylesheet" href="{{ filemanager_asset('css/jquery.fileupload-noscript.css') }}"></noscript>
    <noscript><link rel="stylesheet" href="{{ filemanager_asset('css/jquery.fileupload-ui-noscript.css') }}"></noscript>
    <link rel="stylesheet" href="{{ filemanager_asset('css/style.css') }}">

    <script src="{{ filemanager_asset('js/jquery.min.js') }}"></script>
    <script src="{{ filemanager_asset('js/jquery-ui.min.js') }}"></script>
    <script src="{{ filemanager_asset('js/plugins.js') }}"></script>
    <script src="{{ filemanager_asset('js/modernizr.custom.js') }}"></script>

    <script>
        var ext_img = @json(config('filemanager.extensions.image', []));
        var image_editor = false; {{-- TUI image editor dropped from this package; keep the guard include.js checks --}}
    </script>

    <script src="{{ filemanager_asset('js/include.js') }}"></script>

    <script>
        $(function () {
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

            $('#info').off('click').on('click', function(e) {
                e.preventDefault();
                bootbox.alert('<div class="text-center"><br/><h4><strong>OpenSID File Manager</strong></h4><p><a href="https://opendesa.id" target="_blank" rel="noopener">opendesa.id</a></p><br/><p>Pengembang: <strong>Tim Pengembang OpenDesa</strong></p><p>Hak Cipta &copy; 2016 - {{ date("Y") }} <a href="https://opendesa.id" target="_blank" rel="noopener">Perkumpulan Desa Digital Terbuka</a></p><br/><p><small>Lisensi: <a href="http://www.gnu.org/licenses/gpl.html" target="_blank" rel="noopener">GPL v3</a></small></p></div>');
            });

            $(document).on('click', '.sorting-btn, [data-toggle="dropdown"]', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $parent = $(this).closest('.btn-group, .dropdown');
                var isOpen = $parent.hasClass('open');
                $('.btn-group.open, .dropdown.open').removeClass('open');
                if (!isOpen) {
                    $parent.addClass('open');
                }
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('.btn-group, .dropdown').length) {
                    $('.btn-group.open, .dropdown.open').removeClass('open');
                }
            });
        });
    </script>

    <style>
        /* Modern Reset & Base */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            padding-top: 50px;
        }

        /* Navbar & Top Toolbar */
        .navbar-fixed-top {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            margin-bottom: 0;
        }
        .navbar-inner {
            background: #ffffff !important;
            background-image: none !important;
            border-bottom: 1px solid #e2e8f0 !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
            min-height: 46px;
            padding: 3px 12px;
        }
        .navbar .brand {
            display: none;
        }
        .navbar .btn {
            background: #ffffff;
            background-image: none;
            border: 1px solid #cbd5e1;
            color: #475569;
            border-radius: 6px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            padding: 5px 10px;
            transition: all 0.15s ease;
            text-shadow: none;
        }
        .navbar .btn:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #0f172a;
        }
        .navbar .btn.upload-btn {
            background: #3b82f6 !important;
            border-color: #2563eb !important;
            color: #ffffff !important;
            font-weight: 500;
        }
        .navbar .btn.upload-btn:hover {
            background: #2563eb !important;
        }
        .navbar .btn.upload-btn i {
            filter: brightness(0) invert(1);
        }
        .navbar .btn.btn-inverse {
            background: #0f172a !important;
            border-color: #0f172a !important;
            color: #ffffff !important;
        }

        /* Filter & Search Bar */
        .filters .types {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            min-height: 38px;
            gap: 6px;
        }
        .filters .types span {
            margin: 0 !important;
            line-height: 1;
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
        }
        .filters .types input#filter-input {
            margin: 0 !important;
            height: 30px;
            padding: 4px 12px;
            font-size: 13px;
            border-radius: 20px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #1e293b;
            outline: none;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.03);
            transition: all 0.2s ease;
        }
        .filters .types input#filter-input:focus {
            background: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .filters .types label.btn {
            padding: 4px 8px;
            border-radius: 6px;
            margin: 0;
            font-size: 12px;
        }

        /* Breadcrumbs Container & Alignment */
        .breadcrumb-container {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 6px 12px;
            margin: 10px 0 14px 0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 42px;
            box-sizing: border-box;
            position: relative;
            z-index: 50;
        }
        .breadcrumb {
            background: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            box-shadow: none !important;
            display: flex !important;
            align-items: center !important;
            gap: 4px;
            list-style: none;
            overflow: visible !important;
        }
        .breadcrumb > li {
            text-shadow: none;
            display: inline-flex !important;
            align-items: center !important;
            line-height: 1 !important;
            margin: 0;
        }
        .breadcrumb > li a {
            color: #64748b;
            text-decoration: none;
            font-weight: 500;
            font-size: 13px;
            padding: 4px 6px;
            border-radius: 4px;
            display: inline-flex !important;
            align-items: center !important;
            transition: color 0.15s ease, background 0.15s ease;
        }
        .breadcrumb > li a:hover {
            color: #2563eb;
            background: #eff6ff;
        }
        .breadcrumb > li.active {
            color: #0f172a;
            font-weight: 600;
            font-size: 13px;
            padding: 4px 6px;
        }
        .breadcrumb .divider {
            color: #cbd5e1;
            padding: 0 2px;
            display: inline-flex;
            align-items: center;
        }
        .breadcrumb small {
            background: #f1f5f9;
            color: #64748b;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 6px;
            display: inline-flex;
            align-items: center;
            line-height: 1.2;
        }
        .breadcrumb-actions {
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px;
        }
        .bc-action-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            height: 30px;
            padding: 0 8px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            text-decoration: none !important;
            font-size: 13px;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
            gap: 4px;
        }
        .bc-action-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #0f172a;
        }
        .bc-action-btn i {
            margin: 0 !important;
            line-height: 1 !important;
            vertical-align: middle !important;
        }
        .bc-action-btn .caret {
            margin-top: 0 !important;
            margin-left: 3px !important;
            vertical-align: middle !important;
        }
        i[class^="icon-"], i[class*=" icon-"] {
            vertical-align: middle !important;
            margin-top: 0 !important;
            display: inline-block;
        }

        /* Dropdown & Menus */
        .btn-group {
            position: relative;
            display: inline-block;
            vertical-align: middle;
        }
        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 99999;
            display: none;
            float: left;
            min-width: 160px;
            padding: 6px 0;
            margin: 4px 0 0;
            list-style: none;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }
        .btn-group.open > .dropdown-menu {
            display: block !important;
        }
        .dropdown-menu.pull-right {
            right: 0 !important;
            left: auto !important;
        }
        .dropdown-menu > li > a {
            display: block;
            padding: 6px 16px;
            clear: both;
            font-weight: 500;
            line-height: 18px;
            color: #334155;
            white-space: nowrap;
            text-decoration: none;
            font-size: 12px;
            transition: background 0.12s ease;
        }
        .dropdown-menu > li > a:hover,
        .dropdown-menu > li > a:focus {
            color: #1e293b;
            text-decoration: none;
            background-color: #f1f5f9;
            background-image: none;
        }

        /* Modern Grid & Cards */
        ul.grid {
            padding: 4px 0;
            list-style: none;
        }
        ul.grid li {
            border-radius: 10px !important;
            border: 1px solid #e2e8f0 !important;
            background: #ffffff !important;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
            transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1) !important;
            overflow: hidden;
        }
        ul.grid li:hover {
            border-color: #cbd5e1 !important;
            transform: translateY(-3px) !important;
            box-shadow: 0 10px 20px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04) !important;
        }
        ul.grid li figure {
            border-radius: 10px;
        }
        ul.grid li figure .box {
            background: #ffffff;
            border-top: 1px solid #f1f5f9;
            padding: 4px 6px;
        }
        ul.grid li figure .box h4 {
            color: #1e293b;
            font-weight: 500;
            font-size: 12px;
        }
        ul.grid li figure figcaption {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(4px);
            border-radius: 0 0 9px 9px;
        }
        ul.grid li figure figcaption a {
            border-radius: 4px;
            color: #ffffff !important;
            transition: background 0.15s ease;
        }
        ul.grid li figure figcaption a:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        /* Checkbox */
        .selector label.cont {
            border-radius: 4px;
        }

        /* List View Adjustments */
        .list-view1.grid .file-date, .sorter-container .file-date { width: 120px; right: 185px; font-size: 11px; white-space: nowrap; }
        .list-view1.grid .file-size, .sorter-container .file-size { width: 65px; right: 310px; font-size: 11px; }
        .list-view1.grid .file-extension, .sorter-container .file-extension { width: 55px; right: 380px; font-size: 11px; }
        .list-view1.grid figure .box { padding-right: 440px; }

        /* Modern Lightbox Preview */
        .featherlight-iframe .featherlight-content {
            width: 85vw;
            height: 85vh;
            max-width: 1050px;
            padding: 8px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            background: #ffffff;
        }
        .featherlight-iframe iframe {
            border: none;
            width: 100%;
            height: 100%;
            border-radius: 8px;
        }

        /* Modern Uploader */
        .uploader {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
        }
        .upload-tabbable {
            border-radius: 10px;
            border: 2px dashed #cbd5e1;
            background: #ffffff;
            padding: 20px;
        }
    </style>
</head>
<body>
    {{--
        Config bridge: include.js (unmodified) reads its configuration from
        these hidden inputs rather than from an API call. Keep ids/values in
        lockstep with what that bundle expects.
    --}}
    <input type="hidden" id="ftp" value="">
    <input type="hidden" id="popup" value="{{ $popup ? 1 : 0 }}">
    <input type="hidden" id="callback" value="{{ $callback }}">
    <input type="hidden" id="crossdomain" value="{{ $crossdomain ? 1 : 0 }}">
    <input type="hidden" id="editor" value="{{ $editor }}">
    <input type="hidden" id="view" value="{{ $viewType }}">
    <input type="hidden" id="subdir" value="{{ $subdir }}">
    <input type="hidden" id="field_id" value="{{ $fieldId }}">
    <input type="hidden" id="multiple" value="{{ $multiple }}">
    <input type="hidden" id="type_param" value="{{ $type }}">
@php
    $filesManager = app(\OpenSID\LaravelFilemanager\Services\FilesystemManager::class);
    $mediaBaseUrl = $filesManager->url('');
    $mediaBasePath = trim((string) parse_url($mediaBaseUrl, PHP_URL_PATH), '/');
    $mediaBasePath = $mediaBasePath !== '' ? $mediaBasePath . '/' : '';
    $subdirWithSlash = $subdir !== '' ? trim($subdir, '/') . '/' : '';
    $curDir = '/' . $mediaBasePath . $subdirWithSlash;
@endphp

    <input type="hidden" id="insert_folder_name" value="{{ __('filemanager::filemanager.Insert_Folder_Name') }}">
    <input type="hidden" id="rename_existing_folder" value="{{ __('filemanager::filemanager.Rename_existing_folder') }}">
    <input type="hidden" id="new_folder" value="{{ __('filemanager::filemanager.New_Folder') }}">
    <input type="hidden" id="ok" value="{{ __('filemanager::filemanager.OK') }}">
    <input type="hidden" id="cancel" value="{{ __('filemanager::filemanager.Cancel') }}">
    <input type="hidden" id="rename" value="{{ __('filemanager::filemanager.Rename') }}">
    <input type="hidden" id="lang_duplicate" value="{{ __('filemanager::filemanager.Duplicate') }}">
    <input type="hidden" id="duplicate" value="{{ $canUpload ? 1 : 0 }}">
    <input type="hidden" id="base_url" value="{{ str_replace('/index.php', '', url('/')) }}">
    <input type="hidden" id="upload_dir" value="{{ '/' . $mediaBasePath }}">
    <input type="hidden" id="cur_dir" value="{{ $curDir }}">
    <input type="hidden" id="ftp_base_url" value="">
    <input type="hidden" id="fldr_value" value="{{ $subdirWithSlash }}">
    <input type="hidden" id="sub_folder" value="">
    <input type="hidden" id="return_relative_url" value="{{ $relativeUrl ? 1 : 0 }}">
    <input type="hidden" id="file_number_limit_js" value="500">
    <input type="hidden" id="sort_by" value="{{ $sortBy }}">
    <input type="hidden" id="descending" value="{{ $descending ? 1 : 0 }}">
    <input type="hidden" id="current_url" value="{{ url()->current() }}">
    <input type="hidden" id="lang_show_url" value="{{ __('filemanager::filemanager.Show_url') }}">
    <input type="hidden" id="copy_cut_files_allowed" value="{{ $canDelete ? 1 : 0 }}">
    <input type="hidden" id="copy_cut_dirs_allowed" value="{{ $canDelete ? 1 : 0 }}">
    <input type="hidden" id="copy_cut_max_size" value="false">
    <input type="hidden" id="copy_cut_max_count" value="false">
    <input type="hidden" id="lang_copy" value="{{ __('filemanager::filemanager.Copy') }}">
    <input type="hidden" id="lang_cut" value="{{ __('filemanager::filemanager.Cut') }}">
    <input type="hidden" id="lang_paste" value="{{ __('filemanager::filemanager.Paste') }}">
    <input type="hidden" id="lang_paste_here" value="{{ __('filemanager::filemanager.Paste_Here') }}">
    <input type="hidden" id="lang_paste_confirm" value="{{ __('filemanager::filemanager.Paste_Confirm') }}">
    <input type="hidden" id="lang_files" value="{{ __('filemanager::filemanager.Files') }}">
    <input type="hidden" id="lang_folders" value="{{ __('filemanager::filemanager.Folders') }}">
    <input type="hidden" id="lang_files_on_clipboard" value="{{ __('filemanager::filemanager.Files_ON_Clipboard') }}">
    <input type="hidden" id="clipboard" value="{{ $clipboardHasContent ? 1 : 0 }}">
    <input type="hidden" id="lang_clear_clipboard_confirm" value="{{ __('filemanager::filemanager.Clear_Clipboard_Confirm') }}">
    <input type="hidden" id="lang_lang_change" value="{{ __('filemanager::filemanager.Lang_Change') }}">
    <input type="hidden" id="edit_text_files_allowed" value="{{ config('filemanager.text_editing_enabled') && $canUpload ? 1 : 0 }}">
    <input type="hidden" id="lang_edit_file" value="{{ __('filemanager::filemanager.Edit_File') }}">
    <input type="hidden" id="lang_new_file" value="{{ __('filemanager::filemanager.New_File') }}">
    <input type="hidden" id="lang_filename" value="{{ __('filemanager::filemanager.Filename') }}">
    <input type="hidden" id="lang_file_info" value="{{ __('filemanager::filemanager.File_info') }}">
    <input type="hidden" id="lang_error_upload" value="{{ __('filemanager::filemanager.Error_Upload') }}">
    <input type="hidden" id="lang_select" value="{{ __('filemanager::filemanager.Select') }}">
    <input type="hidden" id="lang_extract" value="{{ __('filemanager::filemanager.Extract') }}">
    <input type="hidden" id="extract_files" value="0">
    <input type="hidden" id="transliteration" value="{{ config('filemanager.transliterate') ? 'true' : 'false' }}">
    <input type="hidden" id="convert_spaces" value="{{ config('filemanager.convert_spaces') ? 'true' : 'false' }}">
    <input type="hidden" id="replace_with" value="{{ config('filemanager.convert_spaces') ? config('filemanager.replace_with') : '' }}">
    <input type="hidden" id="lower_case" value="{{ config('filemanager.lower_case') ? 'true' : 'false' }}">
    <input type="hidden" id="show_folder_size" value="0">
    <input type="hidden" id="add_time_to_img" value="0">

    @if ($canUpload)
        @include('filemanager::partials.upload-panel')
    @endif

    <div class="container-fluid">
        @include('filemanager::partials.toolbar')

        <div class="header-container">
            <div class="sorter-container list-view{{ $viewType }}">
                <a class="sorter sort-name {{ $sortBy == 'name' ? ($descending ? 'descending' : 'ascending') : '' }}" href="javascript:void('')" data-sort="name"><span class="file-name">{{ __('filemanager::filemanager.Filename') }}</span></a>
                <a class="sorter sort-date {{ $sortBy == 'date' ? ($descending ? 'descending' : 'ascending') : '' }}" href="javascript:void('')" data-sort="date"><span class="file-date">{{ __('filemanager::filemanager.Date') }}</span></a>
                <a class="sorter sort-size {{ $sortBy == 'size' ? ($descending ? 'descending' : 'ascending') : '' }}" href="javascript:void('')" data-sort="size"><span class="file-size">{{ __('filemanager::filemanager.Size') }}</span></a>
                <a class="sorter sort-extension {{ $sortBy == 'extension' ? ($descending ? 'descending' : 'ascending') : '' }}" href="javascript:void('')" data-sort="extension"><span class="file-extension">{{ __('filemanager::filemanager.Type') }}</span></a>
                <span class="file-operations">{{ __('filemanager::filemanager.Operations') }}</span>
            </div>
        </div>

        <div class="row-fluid">
            <div class="row-fluid ff-container">
                <div class="span12">
                    <h4 id="help">{{ __('filemanager::filemanager.Swipe_help') }}</h4>

                    <ul class="grid cs-style-2 list-view{{ $viewType }}" id="main-item-container">
                        @include('filemanager::partials.grid')
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div id="loading_container" style="display:none;">
        <div id="loading" style="background-color:#000; position:fixed; width:100%; height:100%; top:0px; left:0px;z-index:100000"></div>
        <img id="loading_animation" src="{{ filemanager_asset('img/storing_animation.gif') }}" alt="loading" style="z-index:10001; margin-left:-32px; margin-top:-32px; position:fixed; left:50%; top:50%">
    </div>

    <script src="{{ filemanager_asset('js/tmpl.min.js') }}" defer></script>
    <script src="{{ filemanager_asset('js/load-image.all.min.js') }}" defer></script>
    <script src="{{ filemanager_asset('js/canvas-to-blob.min.js') }}" defer></script>
    <script src="{{ filemanager_asset('js/jquery.iframe-transport.js') }}" defer></script>
    <script src="{{ filemanager_asset('js/jquery.fileupload.js') }}" defer></script>
    <script src="{{ filemanager_asset('js/jquery.fileupload-process.js') }}" defer></script>
    <script src="{{ filemanager_asset('js/jquery.fileupload-image.js') }}" defer></script>
    <script src="{{ filemanager_asset('js/jquery.fileupload-audio.js') }}" defer></script>
    <script src="{{ filemanager_asset('js/jquery.fileupload-video.js') }}" defer></script>
    <script src="{{ filemanager_asset('js/jquery.fileupload-validate.js') }}" defer></script>
    <script src="{{ filemanager_asset('js/jquery.fileupload-ui.js') }}" defer></script>
</body>
</html>
