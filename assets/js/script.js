function randSign() {
    return 0.5 - Math.random()
}

function shuffle() {
    var values = [],
        items = jQuery('.plughuntQuiz__answers li');

    items.each(function(index) {
        values.push(items.eq(index).html())
    });

    values.sort(randSign);

    items.each(function(index) {
        items.eq(index).html(values[index])
    })
}

function randomInteger(min, max) {
    var rand = min - 0.5 + Math.random() * (max - min + 1);
    rand = Math.round(rand);
    return rand;
}

quiz_end = quiz.end;

window.pc = function(data) {
    jQuery('.plughuntLoading').hide();
    jQuery('.plughuntQuiz__start').hide();
    jQuery('.plughuntQuiz__quiz').show();
};

var point = 0;
var maxPoint = 0;
var qIndex = 0;
var qCount = quiz.questions.length;
var qCorrect = 0;

var btnColor = quiz.settings.btn_color;
var btnTextColor = quiz.settings.btn_text_color;
var btnFontSize = quiz.settings.font_size_btn;

var fontSizeTitle = quiz.settings.font_size_title;
var fontSizeQuestion = quiz.settings.font_size_question;
var btnPadding = quiz.settings.btn_padding;

var nowAnswers = [];

var quizType = quiz.settings.quiz_type;

jQuery('body').on('click', '#plughuntBtn__start', function(e) {
    e.preventDefault();
    pc();
    preloadQuestion(qIndex);
});

function createData(data) {
    setTimeout(function() {
        jQuery('#plughuntBtn__start')
            .html(quiz.settings.btn_start_text)
            .css({
                'background-color': btnColor,
                'font-size': btnFontSize,
                'color': btnTextColor
            })
            .prop('disabled', false);
    }, 200);
    for (var i = 0; i < data.length; i++) {
        var answ = data[i].answers;
        maxPoint += parseInt(answ[0].points);
    }
}

createData(quiz.questions);

function preloadQuestion(index) {
    nowAnswers = [];
    jQuery('.plughuntLoading').hide();
    var obj = quiz.questions.shift();
    var indexHtml = jQuery('.plughuntQuiz__quiz');
    oss = obj;
    let linked_answer;
    indexHtml.empty().show();
    if (obj !== undefined) {
        indexHtml.attr('data-quiz-question-id', obj.id);
        var stepHtml = jQuery('<div/>', {
            'text': qIndex + 1 + '/' + qCount,
            'class': 'plughuntQuiz__progress'
        });
        jQuery(indexHtml).append(stepHtml);
        if (obj.link) {
            if (obj.question) { linked_answer = '<p>' + obj.question + '</p>'; }
            indexHtml.append('<div id="img_after" style="background: url(' + obj.link + ');background-size: cover;"></div>'); //<img id="img_pic_question" src="" width="600" height="600" alt="lorem">
            //jQuery('#img_pic_question').attr("src", obj.link);
        } else {
            indexHtml.append('<div id="gradientQuiz__QuizName" style="background:' + quiz.colors.sc + '; color:' + quiz.colors.stc + '"><div id="gradien_after">' + obj.question + '</div></div>');
        }

        /*indexHtml.append('<div class=\'plughuntQuiz__question\'></div>');
            jQuery('.plughuntQuiz__question').html(obj.question);*/

        if (linked_answer) { indexHtml.append('<ul class=\'plughuntQuiz__answers\'>' + linked_answer + '</ul>'); } else { indexHtml.append('<ul class=\'plughuntQuiz__answers\'></ul>'); }
        for (var i = 0; i < obj.answers.length; i++) {
            if (quizType === 1) {
                nowAnswers.push(obj.answers[i].points)
            }
            var aHtml = jQuery('<li/>', {});
            var idHtml = jQuery('<span/>', {
                'text': obj.answers[i].answer,
                'class': 'plughuntQuiz__answer',
                'style': 'background-color:' + btnColor + ';' +
                    'color:' + btnTextColor + ';' +
                    'font-size:' + btnFontSize + ';'
            }).attr('data-quiz-answer-id', obj.answers[i].id);
            jQuery(aHtml).append(idHtml);
            jQuery('.plughuntQuiz__answers', indexHtml).append(aHtml);
        }
        shuffle();
    } else {
        indexHtml.remove();
    }
}

jQuery('body').on('click', '.plughuntQuiz__answer', function(e) {
    e.preventDefault();
    var i0 = jQuery('.plughuntQuiz__quiz');
    var qqID = i0.attr('data-quiz-question-id');
    var qaID = jQuery(this).attr('data-quiz-answer-id');
    jQuery('.plughuntQuiz__answers').addClass('plughuntQuiz__quiz--disabled');
    jQuery(this).addClass('chooseAnsw');
    point += parseInt(nowAnswers[qaID]);
    qIndex++;
    if (qaID === '0') {
        qCorrect++;
        if (quiz.settings.show_correct_answer && quiz.settings.quiz_type !== 1) {
            jQuery(this).addClass('correctAnsw');
        }
    } else {
        if (quiz.settings.show_correct_answer && quiz.settings.quiz_type !== 1) {
            jQuery(this).addClass('notcorrectAnsw');
            jQuery(i0).find('[data-quiz-answer-id="0"]').addClass('correctAnsw');
        }
    }
    var timeout = 100;
    if (quiz.settings.quiz_delay) {
        timeout = randomInteger(2000, 4000);
    }
    setTimeout(function() {
        //jQuery('.plughuntLoading').show();
        //var indexHtml = jQuery('.plughuntQuiz__quiz');
        var indexHtml = jQuery('.plughuntQuiz__answers');
        indexHtml.empty().hide();
        if (qIndex === qCount) {
            setTimeout(function() {
                getResult();
            }, 700);
        } else {
            setTimeout(function() {
                preloadQuestion(qIndex);
            }, 700);
        }
    }, timeout);
});

function getResult() {
    var end_descr;
    var result_descr;
    if (quizType === 1) {
        var endPersent = point;
        for (var i = 0; i < quiz_end.length; i++) {
            if ((endPersent >= parseInt(quiz_end[i].end_from)) && (endPersent <= parseInt(quiz_end[i].end_to))) {
                if (quiz.end[i].link) {
                    jQuery('.ended_img').css({ "background": "url(" + quiz.end[i].link + ")", "background-size": "cover" });
                    if (quiz_end[i].description) { result_descr = '<p>' + quiz_end[i].description + '</p>'; }
                }
                end_descr = quiz_end[i].description;
            }
        }
    } else {
        for (var i = 0; i < quiz_end.length; i++) {
            if (qCorrect >= parseInt(quiz_end[i].end_from) && qCorrect <= parseInt(quiz_end[i].end_to)) {
                if (quiz.end[i].link) {
                    jQuery('.ended_img').css({ "background": "url(" + quiz.end[i].link + ")", "background-size": "cover" });
                    if (quiz_end[i].description) { result_descr = '<p>' + quiz_end[i].description + '</p>'; }
                }
                //jQuery('.answer_descr').append('<br><p>' + quiz_end[i].description + '</p>');
                end_descr = quiz_end[i].description;
            }
        }
        //console.log(qCorrect)
    }
    jQuery('.plughuntLoading').hide();
    jQuery('.plughuntQuiz__quiz').hide();
    if (result_descr) {
        jQuery('.answer_descr').append(result_descr);
    } else { jQuery('.plughuntQuiz__end--title').html(end_descr); }
    jQuery('.plughuntQuiz__end').show();
}

//Share
var btnShareUrl = window.location.href;
// var shareImage = quiz.settings.image_share_url;
var shareTitle = jQuery('.plughuntQuiz__QuizName').text();
var shareBtn = document.getElementsByClassName('plughuntQuiz__share');

for (var i = 0; i < shareBtn.length; i++)
    shareBtn[i].addEventListener('click', function(e) {
        e.preventDefault();
        reposterShareUrl = this.getAttribute('data-type');
        reposterShare(reposterShareUrl);
    });

function reposterShare(url) {
    switch (url) {
        case 'facebook':
            href = 'https://www.facebook.com/sharer/sharer.php?u=' + btnShareUrl + '&title=' + shareTitle;
            return !window.open(href, 'Facebook', 'width=640,height=300');
            break;
        case 'vkontakte':
            href = 'https://vk.com/share.php?url=' + btnShareUrl + '?title=' + shareTitle;
            return !window.open(href, 'Vkontakte', 'width=640,height=300');
            break;
        default:
    }
}