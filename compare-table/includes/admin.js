function ruigehond014_setup() {
    // sort functionality stolen from ruigehond008 / user-reviews
    (function ($) {
        $('.rows-sortable').sortable({
            opacity: 0.6,
            revert: true,
            cursor: 'grabbing',
            handle: '.sortable-handle',
            start: function () { // please blur any input before sorting to autosave them
                document.activeElement.blur();
            },
        }).droppable({
            greedy: true, // prevent propagation to parents
            drop: function (event, ui) {
                const target = event.target,
                    dropped_id = ui.draggable[0].getAttribute('data-id'),
                    children = target.childNodes,
                    order = {};
                let row, i, data;
                // disable further ordering until return
                $(target).sortable('disable');
                $('.sortable-handle').addClass('disabled');
                $(ui.draggable).addClass('unsaved');
                // get the new order to send to the server for update
                for (i = 0; i < children.length; ++i) {
                    row = children[i];
                    // apparently jquery ui drops a shadowcopy or something which doesn't contain anything useful
                    // and it keeps the original in the DOM, maybe there's a later event that has rearranged everything
                    // proper, but I want to initiate ajax asap, hence we look for the placeholder element where we
                    // put the id the sortable row has been dropped on. In hindsight also kind of logical
                    if (row.getAttribute('data-id') === dropped_id) continue;
                    if (row.getAttribute('data-id') === 0) continue;
                    if (row.getAttribute('data-id') === null) {
                        order[dropped_id] = i;
                    } else {
                        order[row.getAttribute('data-id')] = i;
                    }
                }
                data = {
                    'action': 'ruigehond014_handle_input',
                    'handle': 'order_rows',
                    'table_name': target.getAttribute('data-table_name'),
                    'order': order,
                    'id': dropped_id,
                    'nonce': Ruigehond014.nonce,
                };
                $.ajax({
                    url: ajaxurl,
                    data: data,
                    dataType: 'JSON',
                    method: 'POST',
                    success: function (json) {
                        if (json.success === true) {
                            //console.warn(json);
                            // restore sortability to the table and register save
                            $(target).sortable('enable');
                            $('.sortable-handle').removeClass('disabled');
                            if (json.hasOwnProperty('data') && json.data.hasOwnProperty('id')) {
                                $('[data-id="' + json.data.id + '"]').removeClass('unsaved');
                            }
                        } else {
                            const ntc = new RuigehondNotice('Order not saved, please refresh page');
                            ntc.set_level('error');
                            ntc.popup();
                            console.error(json);
                        }
                    }
                });
            },
        });
        // enhance the input elements to Ruigehond014_input elements
        $.each($('input[type="checkbox"].ruigehond014.ajaxupdate, input[type="text"].ruigehond014.ajaxupdate, textarea.ruigehond014.ajaxupdate, input[type="button"].ruigehond014.ajaxupdate'), function (key, value) {
            value.prototype = new Ruigehond014_input($, value);
        });
// okipokoi
    })(jQuery);
}

/**
 *  copied from ruigehond008..., make this better please
 */
function Ruigehond014_input($, HTMLElement) {
    this.input = HTMLElement;
    this.$input = $(HTMLElement);
    this.$ = $; // cache jQuery to stay compatible with everybody
    this.id = parseInt(this.$input.attr('data-id')) || 0;
    this.ajax = new Ruigehond014Ajax(this);
    // suggestions are disabled when input lacks class ajaxsuggest
    this.suggest = new Ruigehond014InputSuggestions(this);
    const self = this;
    if (HTMLElement.type === 'button') {
        // currently only a delete button exists, so you can assume this is it
        this.$input.off('.ruigehond014').on('click.ruigehond014', function () {
            self.delete();
        });
    } else if (HTMLElement.type === 'checkbox') {
        this.$input.off('.ruigehond014').on('change.ruigehond014', function () {
            console.error('doesn\'t work... value = ' + this.checked);
        });
    } else { // text or textarea
        this.$input.off('.ruigehond014').on('blur.ruigehond014', function (event) {
            self.save(event);
        }).on('keyup.ruigehond014', function (event) {
            self.typed(event);
        }).on('keydown.ruigehond014', function (event) { // prevent form from submitting
            if (event.which === 13 && !event.shiftKey) {
                return false; // jQuery way to stopPropagation and preventDefault at the same time.
            }
        });
    }
}

Ruigehond014_input.prototype.typed = function (e) {
    //console.log(e.which);
    switch (e.which) {
        case 13: // enter
            if (!e.shiftKey) {
                if (this.id === 0) {
                    this.$input.blur(); // when no specific focus, ruigehond will focus on the new row after ajax return
                } else {
                    this.focusNext(this.$('.ruigehond014.tabbed')); // will cause blur on this element which causes save
                }
            }
            break;
        case 27: // escape
            this.escape();
            break;
        case 38: // arrow up
            this.suggest.previous();
            break;
        case 40: // arrow down
            this.suggest.next();
            break;
        default:
            this.suggest.filter();
    }
    this.checkChanged();
};

Ruigehond014_input.prototype.getData = function () {
    // returns an object containing all 'data' attributes
    const self = this,
        data = {};
    this.$input.each(function () {
        self.$.each(this.attributes, function () {
            // this.attributes is not a plain object, but an array
            // of attribute nodes, which contain both the name and value
            if (this.name.slice(0, 5) === 'data-') {
                data[this.name.slice(5)] = this.value;
            }
        });
        // get index from the parent row, if you have it // todo do we even need this index?
        let rows, current_row, idx, len;
        if ((current_row = self.$input.parents('.row')).length === 1) {
            // console.log(self.$(current_row).parents('.global_option.'+data['option_name']).find('.row'));
            if ((rows = self.$(current_row).parents('.global_option.' + data['option_name']).find('.row'))) {
                for (idx = 0, len = rows.length; idx < len; ++idx) {
                    if (rows[idx] === current_row.get(0)) {
                        data['index'] = idx;
                        break;
                    }
                }
            }
        }
    });
    return data;
};

Ruigehond014_input.prototype.delete = function () { // this can actually delete several things, depending on handle
    const data = this.getData(),
        self = this;
    this.ajax.call(data, function (json) {
        const data = json.data, data_handle = data.handle;
        if ('clear_offer' === data_handle) {
            // clear all the values...
            document.querySelectorAll('input[data-handle="update_offer"]').forEach(function (el) {
                el.value = '';
            });
        } else if ('undelete' === data_handle) {
            self.$input.parents('.' + data.table_name + '-row').removeClass('marked-for-deletion');
        } else if ('delete_permanently' === data_handle) {
            self.$input.parents('.' + data.table_name + '-row').fadeOut(432, function() {
                this.remove();
            });
        } else if ('delete_array_option' === data_handle) {
            self.$input.parents('.row').remove();
        } else {
            self.$input.parents('.' + data.table_name + '-row').addClass('marked-for-deletion'); // <- indicate it's deleting at the moment
        }
    });
};
Ruigehond014_input.prototype.saveBooleanOption = function () {
    const data = this.getData(),
        self = this;
    self.$input.addClass('unsaved');
    data.value = (this.input.checked ? 1 : 0);
    this.ajax.call(data, function (json) {
        if (json.success === true) self.$input.removeClass('unsaved');
    });
};

Ruigehond014_input.prototype.save = function (e) {
    this.suggest.remove();
    if (this.hasChanged()) {
        console.log('Send update to server.');
        const self = this;
        // handle input based on data
        const data = this.getData();
        data.disable = true;
        data.value = this.$input.val();
        this.ajax.call(data, function (json) {
            self.suggest.remove();
            if (json.data) {
                if (0 === self.id) {
                    // new id is returned by server
                    self.id = json.data.id;
                    // add row
                    self.$input.parent().before(json.data.html);
                    // clear input
                    self.$input.val('');
                    self.$input.removeClass('unsaved');
                    self.$input.removeAttr('disabled');
                    // (re-)activate handlers for input
                    ruigehond014_setup(); // TODO you could only assign the prototypes to the new input elements
                    // if there is no focus yet, focus on the value of the new row
                    if (document.activeElement.tagName === 'BODY') { // there is no specific focus
                        self.$('.ruigehond014.input.tag[data-id="' + self.id.toString() + '"]').focus();
                    }
                } else { // update existing
                    self.updateInput(json.data.value);
                    if (json.data.nonexistent) {
                        self.$input.addClass('nonexistent');
                    } else {
                        self.$input.removeClass('nonexistent');
                    }
                }
            } else {
                console.error('Expected object "data" in response, but not found');
            }
        })
    }
    this.checkChanged();
};

Ruigehond014_input.prototype.updateInput = function (value) {
    this.$input.attr({
        'value': value,
        //placeholder: value,
        'data-value': value,
    });
    this.checkChanged();
};

Ruigehond014_input.prototype.escape = function () {
    this.suggest.remove();
    this.$input.val(this.$input.attr('data-value'));
};

Ruigehond014_input.prototype.focusNext = function ($tabbed) {
    // focus on the next .tabbed item
    let found = false, i, len;
    for (i = 0, len = $tabbed.length; i < len; ++i) {
        if (found === true) {
            $tabbed[i].focus();
            return;
        } else if ($tabbed[i] === this.input) {
            found = true;
        }
    }
    this.input.blur();
};
Ruigehond014_input.prototype.checkChanged = function () {
    if (this.hasChanged()) {
        this.$input.addClass('unsaved'); // class will only be added once, no need to check if it's present already
    } else {
        this.$input.removeClass('unsaved');
    }
};
Ruigehond014_input.prototype.hasChanged = function () {
    if (this.$input.attr('data-value') === this.$input.val()) {
        return false;
    } else if (this.$input.attr('data-id') === '0' && this.$input.val() === '') {
        return false; // new property with value of '' means no change
    } else if (!this.$input.attr('data-value') && !this.$input.val()) {
        return false; // everything's empty
    } else {
        return true;
    }
};

function Ruigehond014Ajax(ruigehond_input) {
    // it receives a Ruigehond014_input instance, you can get all info from there
    this.hond = ruigehond_input;
    this.post_id = ruigehond_input.$("#post_ID").val();
}

Ruigehond014Ajax.prototype.showMessages = function (json) {
    if (false === json.hasOwnProperty('messages')) return;
    for (let i = 0, len = json.messages.length; i < len; ++i) {
        const msg = json.messages[i],
            ntc = new RuigehondNotice(msg.text);
        ntc.set_level(msg.level);
        ntc.popup();
    }
}
Ruigehond014Ajax.prototype.call = function (data, callback) {
    const self = this,
        hond = this.hond,
        $input = hond.$input,
        // keep track of ajax communication
        timestamp = Date.now();
    data.action = 'ruigehond014_handle_input';
    data.post_id = this.post_id;
    data.timestamp = timestamp;
    data.nonce = Ruigehond014_global.nonce;
    $input.attr({'data-timestamp': timestamp});
    if (data.disable === true) {
        $input.attr({'disabled': 'disabled'});
    }
    // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
    jQuery.ajax({
        url: ajaxurl,
        data: data,
        dataType: 'JSON',
        method: 'POST',
        success: function (json) {
            if (!json.data || json.data.timestamp === $input.attr('data-timestamp')) { // only current (ie last) ajax call is valid
                if (json.success) { // update succeeded
                    if (typeof callback === 'function') {
                        callback(json);
                        // TODO might be cleaner to use "this" in the calling code, using callbackObj like suggest.filter
                    }
                } else { // update failed
                    // show fail messages, maybe a confirmation is needed?
                    console.warn('No success.');
                }
                $input.removeAttr('disabled');
                $input.removeAttr('data-timestamp');
                // returnobject can have 1 question which requires feedback with 2 or more answers
                if (json.question) {
                    const p = new RuigehondModal(hond, json.question);
                    p.popup();
                }
            } else {
                console.warn('timestamp ' + json.data.timestamp + ' incorrect, need: ' + $input.attr('data-timestamp'))
            }
            self.showMessages(json);
        },
        error: function (json) {
            $input.removeAttr('disabled');
            $input.removeAttr('timestamp');
            self.showMessages(json);
            console.error(json);
        }
    });
};

function Ruigehond014InputSuggestions(ruigehond_input) {
    // it receives a Ruigehond014
    //_input instance, you can get all info from there
    this.hond = ruigehond_input;
    this.disabled = !this.hond.$input.hasClass('ajaxsuggest');
    if (!this.disabled) {
        this.suggest_column = this.hond.$input.attr('data-column_name');
        this.suggest_id = 'datalist_' + this.suggest_column + '_' + this.hond.id;
        this.lastTyped = '';
    }
    // don't initialize here, for all the ajax calls slow down, initialize JIT
}

Ruigehond014InputSuggestions.prototype.hasDatalist = function () {
    return (this.hond.$('#' + this.suggest_id).length === 1);
};
Ruigehond014InputSuggestions.prototype.next = function () {
    if (this.disabled) return;
    if (!this.hasDatalist()) {
        this.initialize(this.next, this);
    } else {
        var $current_suggestion = this.hond.$('#' + this.suggest_id + ' li.selecting');
        if ($current_suggestion.length) {
            var $next = $current_suggestion.nextAll(':visible').first();
            if ($next) {
                this.hond.$('#' + this.suggest_id + ' li').removeClass('selecting');
                $next.addClass('selecting');
            }
        } else {
            this.hond.$('#' + this.suggest_id + ' li:visible').first().addClass('selecting');
        }
        this.hond.$input.val(this.getCurrent() || this.lastTyped);
    }
};
Ruigehond014InputSuggestions.prototype.previous = function () {
    if (this.disabled) return;
    if (!this.hasDatalist()) {
        this.initialize(this.previous, this);
    } else {
        var $current_suggestion = this.hond.$('#' + this.suggest_id + ' li.selecting');
        if ($current_suggestion.length) {
            var $prev = $current_suggestion.prevAll(':visible').first();
            if ($prev) {
                this.hond.$('#' + this.suggest_id + ' li').removeClass('selecting');
                $prev.addClass('selecting');
            }
        } else {
            this.hond.$input.focus();
        }
        this.hond.$input.val(this.getCurrent() || this.lastTyped);
    }
};
Ruigehond014InputSuggestions.prototype.getCurrent = function () {
    return this.hond.$('#' + this.suggest_id + ' li.selecting input').val();
};
Ruigehond014InputSuggestions.prototype.filter = function () {
    if (this.disabled) return;
    if (!this.hasDatalist()) {
        this.initialize(this.filter, this);
    } else {
        var value = this.hond.$input.val(),
            _this = this;
        this.lastTyped = value;
        this.hond.$('#' + this.suggest_id + ' li').css({'display': 'none'}).filter(function () {
            return _this.hond.$(this).find('input').val().toLowerCase().indexOf(value.toLowerCase()) >= 0;
        }).css({'display': 'block'});
    }
    // if no suggestions are visible, hide the list, scrollbars remain visible otherwise
    if (this.hond.$('#' + this.suggest_id + ' li:visible').length === 0) {
        this.hond.$('#' + this.suggest_id).css({'visibility': 'hidden'});
    } else {
        this.hond.$('#' + this.suggest_id).css({'visibility': 'visible'});
    }
};
Ruigehond014InputSuggestions.prototype.remove = function () {
    try {
        this.hond.$('#' + this.suggest_id).remove();
    } catch (e) {
    }
};
Ruigehond014InputSuggestions.prototype.initialize = function (callback, callbackObj) {
    if (this.disabled) return;
    // you can fetch the whole list just once, so no repeated ajax calls for suggestions please, just wait for the first one to come back
    if (this.calling) return;
    this.calling = true;
    data = this.hond.getData();
    data.handle = 'suggest_' + this.suggest_column;
    var _this = this;
    this.hond.ajax.call(data, function (json) {
        if (_this.hond.input !== document.activeElement) return; // too late, user moved on
        if (!_this.hasDatalist()) { // if not exists, add the datalist
            // TODO possible bug when busy and another ajax call comes back right before the id is added to the dom
            var $input = _this.hond.$input;
            console.log(json);
            $input.before($input, '<ul id="' + _this.suggest_id + '" class="ruigehond datalist"></ul>');
            // now add suggestions received by server as options to the list
            var $datalist = _this.hond.$('#' + _this.suggest_id);
            var handle = json.data.column_name; //_this.hond.handle;
            if (json.suggestions) {
                for (var i = 0; i < json.suggestions.length; ++i) {
                    if (json.suggestions[i][handle] === '') {
                        console.warn('Empty string for suggestion ' + handle);
                    } else {
                        // the added input element is because of utf-8 symbols being rendered as emojis in plain html
                        // https://stackoverflow.com/questions/32915485/how-to-prevent-unicode-characters-from-rendering-as-emoji-in-html-from-javascrip
                        $datalist.append('<li><input value="' + json.suggestions[i][handle].replaceAll('"', '&quot;') + '"/></li>');
                    }
                }
            }
            $datalist.css({
                'left': Math.floor($input.position().left) + 'px',
                'top': Math.floor($input.position().top + $input.height()) + 'px'
            });
            _this.hond.$('#' + _this.suggest_id + ' li').off('mousedown').on('mousedown', function () {
                $input.val(_this.hond.$(this).find('input').val()).blur(); // here this is the li element
                return false; // prevent default etc.
            });
            // make the list disappear if the user clicks somewhere else
            _this.hond.$(document).off('.ruigehond014.datalist.' + _this.suggest_id).on('mouseup.ruigehond014.datalist.' + _this.suggest_id, function () {
                _this.remove();
            });
        }
        if (typeof callback === 'function') {
            callback.apply(callbackObj);
        }
        _this.calling = false;
    });
};

function RuigehondModal(ruigehond_input, question_as_json) {
    this.q = {};
    this.answers = [];
    this.question = 'Modal';
    this.hond = ruigehond_input; // the ruigehond that called this modal
    if (typeof question_as_json === 'object') {
        this.set_question(question_as_json);
    }
}

RuigehondModal.prototype.set_question = function (question_as_json) {
    this.q = question_as_json;
    this.question = this.q.text;
    var a = this.q.answers;
    for (var i = 0; i < a.length; ++i) {
        this.answers[i] = a[i];
    }
};
RuigehondModal.prototype.popup = function () {
    var _this = this,
        $w = this.hond.$(document.createElement('div')), // wrapper
        i, len;
    _this.close();
    $w.attr({
        class: 'ruigehond modal wrapper'
    });
    $w.on('click', function () {
        _this.close();
    });
    var $d = this.hond.$(document.createElement('div')); // dialog
    $d.attr({
        id: 'RuigehondModal',
        class: 'ruigehond modal dialog'
    });
    var $c = this.hond.$(document.createElement('button'));
    $c.attr({
        type: 'button',
        class: 'notice-dismiss'
    });
    $c.on('click', function () {
        _this.close();
    });
    $d.append($c);
    $d.append('<h1>' + this.question.toString() + '</h1>');
    for (i = 0, len = this.answers.length; i < len; ++i) {
        $d.append(this.answer_element(this.answers[i]));
    }
    this.hond.$('body').append($w, $d);
    if (i > 0) { // focus on the last answer button so a simple 'enter' suffices for the default action
        $d.children(i).focus();
    }
};
RuigehondModal.prototype.answer_element = function (answer) {
    var _this = this;
    var $b = this.hond.$(document.createElement('input'));
    $b.attr({
        type: 'button',
        value: answer.text,
        class: 'button',
    });
    if (answer.data) {
        $b.attr({
            'data-id': answer.data.id,
            'data-handle': answer.data.handle
        });
        $b.on('mouseup keyup', function (event) {
            if (event.type === 'keyup' && event.key !== 'Enter') return;
            //console.log(_this.hond.$input);
            _this.hond.ajax.call(answer.data, function (json) {
                if (json.data) {
                    if (json.data.handle === 'undelete') {
                        _this.hond.$input.parents('.' + json.data.table_name + '_row').removeClass('marked-for-deletion');
                    } else if (json.data.handle === 'delete_permanently') {
                        _this.hond.$input.parents('.' + json.data.table_name + '_row').css({'display': 'none'});
                    } else {
                        _this.hond.updateInput(json.data.value);
                    }
                } else {
                    console.error('Expected object "data" in response, but not found');
                }
                _this.close();
            });
            //ruigehond008_handleinput(event, _this.hond.$input, answer.data)
        });
    } else {
        $b.on('mouseup', function () { // always close the dialog
            _this.close();
        });
    }
    return $b;
};

RuigehondModal.prototype.close = function () {
    var _this = this;
    this.hond.$('.ruigehond.modal').fadeOut(300, function () {
        _this.hond.$(this).remove();
        _this.hond.$input.focus();
    });
};

function RuigehondNotice(text_as_string, level) {
    const self = this;
    this.text = text_as_string;
    this.level = level || 'log';
    (function ($) {
        self.$ = $;
    })(jQuery);
}

RuigehondNotice.prototype.popup = function () {
    let $n = this.$('.ruigehond.notices').first();
    if ($n.length === 0) { // create notices container if not present
        $n = this.$(document.createElement('div'));
        $n.attr({
            'class': 'ruigehond notices'
        });
        this.$('body').append($n);
    }
    // display this notice
    const $d = this.$(document.createElement('div'));
    $d.attr({
        'class': 'ruigehond notice ' + this.level
    });
    $d.html(this.text);
    // show the message
    this.$($n).append($d);
    this.$element = $d;
    const self = this;
    if (this.level === 'log') { // hide ok messages after a while
        setTimeout(function () {
            self.hide();
        }, 2000);
    } else { // TODO make message dismissible with a button
        setTimeout(function () {
            self.hide();
        }, 3000);
    }
};
RuigehondNotice.prototype.hide = function () {
    try {
        const self = this;
        this.$element.fadeOut(300, function () {
            self.$(this).remove();
        });
    } catch (e) {
        // fail silently
    }
};
RuigehondNotice.prototype.set_level = function (level) {
    this.level = level;
};
/**
 * end of copied from ruigehond008
 */

/* only after everything is locked and loaded we’re initialising */
if (document.readyState === 'complete') {
    ruigehond014_setup();
} else {
    window.addEventListener('load', function (event) {
        ruigehond014_setup();
    });
}