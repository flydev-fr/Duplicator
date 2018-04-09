/************************************************/
var $debug = false; // set to TRUE for debug mode (not used here)
/************************************************/
if (typeof jQuery != 'undefined') {

    $(document).ready(function () {
        
        var $backupNowBtn = $('#backupNow'),
            $pruneBackupsBtn = $('#pruneBackups'),
            $job = false,
            $pageHasBeenModified = false;

        $backupNowBtn.on('click', function (ev) {
            if (ev.originalEvent.defaultPrevented) return;
            if($pageHasBeenModified == true) {
                alert('Duplicator settings have been modified.\n\nSave the module settings before continuing or refresh the page to discard settings.\n\n');
                return;
            }
            if ($job == true) {
                alert('A job is already running');
                return;
            }
            if (!$('input[name=useLocalFolder]').is(':checked') && !$('input[name=useGoogleDrive]').is(':checked') && !$('input[name=useDropbox]').is(':checked') && !$('input[name=useFTP]').is(':checked') && !$('input[name=useAmazonS3]').is(':checked')) {
                redirectToDuplicator('You must choose one or more option where to save packages and save the module.', 'warning');
            }
            $.ajax({
                    url: $(this).data('action'),
                    context: document.body,
                    beforeSend: function () {
                        $job = true;
                        $backupNowBtn.text('').append($("<i class='fa fa-spinner fa-spin'></i>")).append(' Backing up ...');
                    }
                })
                .fail(function (xhr, status, error) {
                    $job = false;
                    $backupNowBtn.removeClass("ui-state-active").text('Backup now');
                    var err = eval("(" + xhr.responseText + ")");
                    redirectToDuplicator('An error occured: ' + err.Message, 'error');
                })
                .done(function () {
                    $job = false;
                    $backupNowBtn.removeClass("ui-state-active").text('Backup now');
                    //redirectToDuplicator('', 'none');
                })
        });
        $pruneBackupsBtn.on('click', function (ev) {
            if ($job == true) {
                alert('A job is already running');
                return;
            }
            $.ajax({
                    url: $(this).data('action'),
                    context: document.body,
                    beforeSend: function () {
                        $job = true;
                        $pruneBackupsBtn.text('').append($("<i class='fa fa-spinner fa-spin'></i>")).append(' Cleaning ...');
                    }
                })
                .fail(function (xhr, status, error) {
                    $job = false;
                    $pruneBackupsBtn.removeClass("ui-state-active").text('Clean packages');
                    var err = eval("(" + xhr.responseText + ")");
                    redirectToDuplicator('An error occured: ' + err.Message, 'error');
                })
                .done(function () {
                    $job = false;
                    $pruneBackupsBtn.removeClass("ui-state-active").text('Clean packages');
                    //redirectToDuplicator('', 'none');
                })
        });

        $('li#wrap_Inputfield_cycle').addClass('invisible');
        if ($('#cronMode_LazyCron').is(':checked')) {
            $('li#wrap_Inputfield_cycle').removeClass('invisible');
        }
        $('#wrap_cronMode').on('click', function () {
            if ($('#cronMode_LazyCron').is(':checked')) {
                $('li#wrap_Inputfield_cycle').removeClass('invisible');
            } else if ($('#cronMode_PWCron').is(':checked') || $('#cronMode_none').is(':checked')) {
                $('li#wrap_Inputfield_cycle').addClass('invisible');
            }
        });

        //wrap_Inputfield_cycle

        function redirectToDuplicator(str, type) {

            var href = '';
            var encoded = encodeURI(str);

            switch (type) {
                case 'none':
                    href = $backupNowBtn.data('action').replace('&action=backup_now', '');
                    break;
                default:
                    href = $backupNowBtn.data('action').replace('&action=backup_now', '&action=' + type + '&msg=' + encoded);
                    break;
            }

            window.location.replace(href);
        }

        $("input, select, textarea").live("click", function() {
            $pageHasBeenModified = true;
        });

    });

}

