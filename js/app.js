function build_matrix() {
    "use strict";

    var matrix = [];
    var items = $('.img_wrap');
    var margin = $('.img_wrap').css('margin');

    margin = margin.slice(0, margin.length - 2);




    //cols
    for (var index = 0; index < items.length; index++) {
        if ($(items[index]).offset().left == margin) {
            //rows
            var row = [];
            row.push(items[index]);
            for (var j = index + 1; j < items.length; j++) {
                if ($(items[j]).offset().left != margin) {
                    row.push(items[j]);
                } else {
                    break;
                }
            }
            matrix.push(row);
        }
    }
    return matrix;
}

function get_width(row, minus_cell) {
    "use strict";

    var width = 0;
    for (var i = 0; i < row.length - (minus_cell || 0); i++) {
        width += $(row[i]).outerWidth(true);
    }

    return width;
}

function resize() {
    "use strict";

    console && console.log("Resize Wall");

    //Сброс
    $('.img_wrap').css('width', 'auto');

    var container_width = $('#wall').width();
    var matrix = build_matrix();

    for (var index = 0; index < matrix.length; index++) {

        var row_width = get_width(matrix[index]);

        if (container_width == row_width) {
            continue;
        }

        if(!matrix[index + 1]){
            continue;
        }

        var new_width = row_width;

        if(row_width < container_width){
            do{
                matrix[index].push(matrix[index + 1][0]);

                //Удалеям первую ячейку из следующие строки
                matrix[index + 1] = matrix[index + 1].slice(1);

                new_width = get_width(matrix[index]);

            }while(new_width <= container_width);
        }

        //Сжимаем строку
        $(matrix[index]).each(function () {

            var diff = matrix[index].length * 10;

            $(this).width($(this).width() * ((container_width - diff) / (new_width - diff) ));
        });

        matrix = build_matrix();
    }
}

function uploadFile(files) {
    "use strict";

    var def = jQuery.Deferred();

    for (var i = 0; i < files.length; i++) {
        if (!files[i].type.match('text/plain')) {
            throw "Неправильный формат";
        } else {

            var data = new FormData();
            data.append('payload', files[i]);

            var xhr = new XMLHttpRequest();

            xhr.open("POST", '/upload');
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4 && xhr.status == 201) {
                    def.resolve(JSON.parse(xhr.responseText));
                }
            };
            xhr.send(data);
        }
    }

    return def;
}

$(function () {
    "use strict";

    $(window).resize(resize);

    var dropzone = document.getElementById('dropzone');

    if (typeof(window.FileReader) == 'undefined') {
        dropzone.innerText = 'Drag & Drop Не поддерживается браузером!';
    }

    dropzone.ondragover = $('#dropzone').d[0].ondragenter = function (event) {
        event.stopPropagation();
        event.preventDefault();
    };

    dropzone.ondrop = function (event) {

        event.preventDefault();
        event.stopPropagation();

        try {
            uploadFile(event.dataTransfer.files).done(function (response) {
                var els = [];
                for (var index in response.files) {
                    els.push($('<div class="img_wrap"><img src="' + '/images/' + response.files[index] + '"/></div>'));
                }
                if (els.length > 0) {
                    $('#wall').append(els);
                }

                console && console.log("Uploaded and appended to DOM");
                setTimeout(function(){
                    resize();
                },50);
            });
        } catch (e) {
            var messages = document.getElementById('messages');
            messages.innerText = e;
        }


    };
});
