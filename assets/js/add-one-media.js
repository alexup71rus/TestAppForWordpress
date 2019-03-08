var cur_b;
var cur_tab;
var select_link_answer;
jQuery(function($) {

    var frame;

    // Прикрепить файл к редакции
    $(document).on('click', '.js-add-file', function(event) {
        event.preventDefault();
        // Если окно загрузки уже доступно, просто откроем его
        if (frame) {
            frame.open();
            return;
        }

        // В противном случае, создадим новое
        frame = wp.media({
            title: 'Выберите файл',
            button: {
                text: 'Использовать этот файл'
            },
            multiple: false // Если нужна возможность крепить одним махом несколько файлов
        });
        var id_button_select = $(this).attr('id').substring(14, $(this).attr('id').length - 1);
        // показать инфу о прикрепленном файле
        frame.on('select', function() {
            // Получим объект со всей информацией о выбранном файле
            var attachment = frame.state().get('selection').first().toJSON();
            console.log(id_button_select);

            if (cur_tab === 1 || !cur_tab) {
                if (select_link_answer > 0) {
                    document.getElementById('answer_image[' + select_link_answer + ']').value = attachment.url;
                } else {
                    document.getElementById('test_image[' + cur_b + ']').value = attachment.url;
                }
            } else {
                if (select_link_answer > 0) {
                    document.getElementById('answer_image2[' + select_link_answer + ']').value = attachment.url;
                } else {
                    document.getElementById('test_image2[' + cur_b + ']').value = attachment.url;
                }
            }
            //select_link_answer = 0;
            /*$('.js-add-wrap').html('<div class="add_file js-add_file_itm">' +
            	'<input type="hidden" name="add_file_id" value="' + attachment.id + '" />' +
            	'<p class="add_file_name">' + attachment.title + '</p>' +
            	'<a href="#" class="button button-primary button-large js-add-file-remove">Открепить файл</a>' +
            	'</div>');
            	*/

        });

        // Откроем файл
        frame.open();
    });

    // отцепить приложенный файл от редакции
    $(document).on('click', '.js-add-file-remove', function(event) {
        event.preventDefault();
        $(this).closest('.js-add_file_itm').remove();
    });
});