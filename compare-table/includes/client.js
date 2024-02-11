/* for each compare-table that might exist, go ahead and make it interactive */
function ruigehond014_compare_tables() {
    // restore scroll position
    const y = localStorage.getItem('ruigehond014_scrollY');
    const tables = document.querySelectorAll('[data-ruigehond014]');
    if (y) {
        if ('scrollBehavior' in document.documentElement.style) {
            window.scrollTo({
                left: 0,
                top: y,
                behavior: 'smooth'
            });
        } else {
            window.scrollTo(0, parseInt(y));
        }
        localStorage.removeItem('ruigehond014_scrollY');
    }
    tables.forEach(function (table) {
        const table_data = JSON.parse(table.dataset.ruigehond014);
        /* validate table data first */
        for (let prop in {
            'type_title': 1,
            'show_columns': 1,
            'show_subjects': 1,
            'all_subjects': 1,
            'alphabetical': 1,
            'choose_subject': 1
        }) {
            if (!table_data.hasOwnProperty(prop)) {
                console.error(`${prop} missing from table_data.`);
                return;
            }
        }
        /* order subjects if necessary */
        if (table_data.alphabetical) {
            table_data.all_subjects.sort();
        }
        /* cross-hair on hover functionality */
        const do_cross_hair = function (td) {
            if (!td) { /* remove all cross-hairs */
                const elements = table.querySelectorAll('.cross-haired');
                const len = elements.length;
                for (let i = 0; i < len; i++) {
                    elements[i].classList.remove('cross-haired');
                }
                return;
            }
            // find out which column we’re in
            const active_row = td.parentNode;
            let index = 0;
            for (let i = 0, len = active_row.children.length; i < len; i++) {
                if (active_row.children[i] === td) {
                    if (0 === i) {
                        index = -1; // first column is never highlighted
                    } else {
                        index = i;
                    }
                    break;
                }
            }
            // switch all rows off, except this row
            const rows = table.querySelectorAll('tr');
            for (let i = 0, len = rows.length; i < len; i++) {
                const row = rows[i];
                if (row === active_row) {
                    row.classList.add('cross-haired');
                } else {
                    row.classList.remove('cross-haired');
                }
                // and the cells: switch off all columns, except this column
                const tdlen = row.children.length;
                for (let tdi = 0; tdi < tdlen; tdi++) {
                    if (tdi === index) {
                        row.children[tdi].classList.add('cross-haired');
                    } else {
                        row.children[tdi].classList.remove('cross-haired');
                    }
                }
            }
        }
        /* make info hovers in cells */
        const cells = table.querySelectorAll('td');
        cells.forEach(function (cell) {
            function hover() {
                const description = this.querySelector('.description');
                const box = cell.getBoundingClientRect();
                if (description) {
                    description.style.left = box.left + 'px';
                    description.style.top = box.top + window.scrollY + 'px';
                    description.classList.add('active');
                }
                /* also: */
                do_cross_hair(this);
            }

            function unhover() {
                const description = this.querySelector('.description');
                if (description) description.classList.remove('active');
            }

            cell.addEventListener('mouseenter', hover, {passive: true});
            cell.addEventListener('mouseleave', unhover, {passive: true});
            cell.addEventListener('touchend', hover, {passive: true});
            const description = cell.querySelector('.description');
            description && description.addEventListener('touchstart', function (e) {
                e.stopPropagation();
            }, {passive: true});
        });
        /* remove cross-hair */
        table.addEventListener('mouseleave', function () {
            do_cross_hair(null);
        });
        /* remove lingering descriptions */
        function cancelhovers() {
            if (!this.querySelector('.description.active')) return;
            const descriptions = this.querySelectorAll('.description.active');
            descriptions.forEach(function (el) {
                el.classList.remove('active');
            });
        }
        table.addEventListener('touchstart', cancelhovers, {passive: true});
        /* startup the select lists in the table headers */
        const select_lists = [];
        for (let i = 0, len = table_data.show_columns; i < len; i++) {
            const selector = new ruigehond014_selector(table, table_data, i);
            select_lists.push(selector);
            /* add to dom */
            const th = table.querySelector('.select.index' + i);
            if (th) {
                const div = document.createElement('div');
                div.classList.add('dddiv' + (i + 1).toString()); // graffitinetwerk
                div.appendChild(selector.el);
                th.appendChild(div);
            } else {
                console.error(`.select.index${i} missing from table.`);
            }
        }
        table_data.select_lists = select_lists;
    });
}

/**
 * @constructor
 * @param table_element
 * @param table_data
 * @param column_index
 * @returns {ruigehond014_selector}
 */
function ruigehond014_selector(table_element, table_data, column_index) {
    const self = this;
    const subjects = table_data.all_subjects;
    const selected = table_data.show_subjects;
    this.column_index = column_index;
    this.table_element = table_element;
    if (subjects.length <= selected.length) {
        this.el = document.createTextNode('');
        return this;
    }
    const el = document.createElement('select');
    el.classList.add('ruigehond014-selector');
    const option = document.createElement('option');
    option.innerHTML = table_data.choose_subject;
    el.appendChild(option);
    for (let i = 0, len = subjects.length; i < len; i++) {
        const option = document.createElement('option');
        const subject = subjects[i];
        option.value = subject;
        option.innerHTML = subject;
        if (-1 === selected.indexOf(subject)) {
            el.appendChild(option);
        }
    }
    el.addEventListener('change', function () {
        self.select(this.options[this.selectedIndex].value);
    });
    this.el = el;
    return this;
}

ruigehond014_selector.prototype.select = function (subject) {
    const href = window.location.href.split('#')[0];
    const parts = href.split('?');
    if (parts.length > 1) {
        const params = parts[1].split('&');
        const len = params.length;
        for (let i = len - 1; i >= 0; i--) {
            if (0 === params[i].indexOf('compare-table-column-' + this.column_index)) {
                delete params[i];
            }
        }
        params.push('compare-table-column-' + this.column_index + '=' + encodeURIComponent(subject));
        parts[1] = Object.values(params).join('&');
        //parts[1] = params.filter(function(item) { return undefined !== item; }).join('&');
    } else {
        parts.push('compare-table-column-' + this.column_index + '=' + encodeURIComponent(subject));
    }
    if (this.hasOwnProperty('table_element')) {
        this.table_element.classList.add('loading');
    }
    // keep scroll position please
    localStorage.setItem('ruigehond014_scrollY', window.scrollY.toString());
    window.location.href = parts.join('?');// + '#'+ this.table_element.id;
}

/* only after everything is locked and loaded we’re initialising */
if ('complete' === document.readyState) {
    ruigehond014_compare_tables();
} else {
    window.addEventListener('load', function () {
        ruigehond014_compare_tables();
    });
}
