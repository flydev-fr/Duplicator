/************************************************/
var $debug = false; // set to TRUE for debug mode
/************************************************/

$(document).ready(function() {

    var $job = false;

    $('#packagesDataTable .btnlink, #deletePackages').tooltip({
        show: null,
        position: {
            my: "left top",
            at: "left bottom"
        },
        open: function (event, ui) {
            ui.tooltip.animate({top: ui.tooltip.position().top + 10}, "fast");
        }
    });

    $('#btnSortByName').click(function (e) {
        var $sort = this;
        var $table = $('#packagesDataTable');

        if ($($sort).hasClass('asc')) {
            $($sort).removeClass('asc');
            sortTable($table, 'asc');
        }
        else {
            $($sort).toggleClass('asc');
            sortTable($table, 'dsc');
        }
        e.preventDefault();

    });

    $('#newPackage').on('click', function (ev) {
        ev.preventDefault();
        backupNow();
    });

    $('#deletePackages').on('click', function (ev) {
        ev.preventDefault();
        $( "#delete-confirm" ).dialog({
            resizable: false,
            height: "auto",
            width: 400,
            modal: true,
            buttons: {
                "Delete all items": function() {
                    deletePackages(true);
                },
                Cancel: function() {
                    $(this).dialog( "close" );
                    $('#deletePackages').removeClass("ui-state-active");
                }
            }
        });
    });

    $('.trashTrigger').on('click', function (ev) {
        var $btn = this;
        ev.preventDefault();
        $( "#delete-confirm-single" ).dialog({
            resizable: false,
            height: "auto",
            width: 400,
            modal: true,
            buttons: {
                "Delete": function() {
                    deleteSinglePackage($btn);
                },
                Cancel: function() {
                    $(this).dialog( "close" );
                }
            }
        });
    });

    function backupNow() {
        if ($job == true) {
            $( "#job-running" ).dialog({
                resizable: false,
                height: "auto",
                width: 400,
                modal: true,
                buttons: {
                    OK: function() {
                        $( this ).dialog( "close" );
                    }
                }
            });
            return;
        }

        var url = $('#newPackage').data('action');
        $.ajax({
                url: url,
                context: document.body,
                beforeSend: function () {
                    $job = true;
                    $('#newPackage').addClass("ui-state-active").text('').append($("<i class='fa fa-spinner fa-spin'></i>")).append(' Processing ...');
                }
            })
            .fail(function (xhr, status, error) {
                $job = false;
                $('#newPackage').removeClass("ui-state-active").text("Create Backup Package");
                var err = eval("(" + xhr.responseText + ")");
                redirectToDuplicator('An error occured: ' + err.Message, 'error');
            })
            .complete(function (html) {
                $job = false;
                $('#newPackage').text("Create Backup Package").removeClass("ui-state-active");
                //redirectToDuplicator('', 'none');
                //$('.modal #content').html(html.responseText).css({"padding-left": "1em", "padding-right": "1em"});
                redirectToDuplicator('', 'packageCreated', '#newPackage');
            });
    }

    function deletePackages() {

        if ($job == true) {
            $( "#job-running" ).dialog({
                resizable: false,
                height: "auto",
                width: 400,
                modal: true,
                buttons: {
                    OK: function() {
                        $( this ).dialog( "close" );
                    }
                }
            });
            return;
        }
        var url = $('#deletePackages').data('action');
        $.ajax({
                url: url,
                context: document.body,
                beforeSend: function () {
                    $job = true;
                    $('#deletePackages').addClass("ui-state-active").text('').append($("<i class='fa fa-spinner fa-spin'></i>")).append(' Deleting ...');
                }
            })
            .fail(function (xhr, status, error) {
                $job = false;
                $('#deletePackages').removeClass("ui-state-active").text("Delete All");
                var err = eval("(" + xhr.responseText + ")");
                redirectToDuplicator('An error occured: ' + err.Message, 'error');
            })
            .done(function () {
                $job = false;
                $('#deletePackages').text("Delete All").removeClass("ui-state-active");
                redirectToDuplicator('', 'none', '#deletePackages');
            });

    }

    function deleteSinglePackage($btn) {

        if ($job == true) {
            $( "#job-running" ).dialog({
                resizable: false,
                height: "auto",
                width: 400,
                modal: true,
                buttons: {
                    OK: function() {
                        $( this ).dialog( "close" );
                    }
                }
            });
            return;
        }
        var url = $($btn).attr('href');
        $.ajax({
                url: url,
                context: document.body,
                beforeSend: function () {
                    $job = true;
                }
            })
            .fail(function (xhr, status, error) {
                $job = false;
                var err = eval("(" + xhr.responseText + ")");
                redirectToDuplicator('An error occured: ' + err.Message, 'error');
            })
            .done(function () {
                $job = false;
                redirectToDuplicator('', 'none', $btn);
            });

    }

    function redirectToDuplicator(str, type, btn) {

        var href = '';

        if($debug === true) return;

        switch (type) {
            case 'none':
                href = $(btn).data('action').replace('?action=backup_now', '');
                break;

            case 'packageCreated':
                href = $(btn).data('action').replace('?action=backup_now', '?action=none');
                break;

            default:
                href = $(btn).data('action');//.replace('?action=backup_now', '?action=' + type + '&msg=' + encoded);
                break;
        }

        window.location.replace(href);
    }

    function sortTable($table,order){
        var $rows = $('tbody > tr', $table);
        $rows.sort(function (a, b) {
            var keyA = $('td', a).text();
            var keyB = $('td', b).text();
            console.log(keyB);
            if (order=='asc') {
                return (keyA > keyB) ? 1 : 0;
            } else {
                return (keyA > keyB) ? 0 : 1;
            }
        });
        $.each($rows, function (index, row) {
            $table.append(row);
        });
    }


    /*
     function step1() {

     var out = '';
     $.ajax({
     url: $('#deploy_step1').data('action'),
     context: document.body
     })
     .fail(function (xhr, status, error) {
     $job = false;
     var err = eval("(" + xhr.responseText + ")");
     out += err;
     return out;
     //redirectToDuplicator('An error occured: ' + err.Message, 'error');
     })
     .done(function (ret) {
     //redirectToDuplicator('', 'none');
     //var err = eval("(" + xhr.responseText + ")");
     $('#content').html(ret);
     $(function() {
     $( "#accordion_server" ).accordion({
     collapsible: true,
     active: false
     });

     $( "#accordion_package" ).accordion({
     collapsible: true,
     active: false
     });
     });
     return ret;
     });
     }
     */

});

