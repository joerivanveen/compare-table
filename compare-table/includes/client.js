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
                    const left = box.left;
                    if (window.innerWidth - left < box.width / 2) {
                        // don’t show it
                    } else {
                        if (left < 0) {
                            description.style.left = '0';
                        } else {
                            description.style.left = left + 'px';
                        }
                        description.style.top = box.top + 'px';
                        description.style.maxWidth = box.width + 'px';
                        description.classList.add('active');
                    }
                }
                /* also: */
                do_cross_hair(this);
            }

            function unhover() {
                const description = this.querySelector('.description');
                if (description) description.classList.remove('active');
            }

            cell.addEventListener('mousemove', hover, {passive: true});
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

        /* remove lingering descriptions on touch devices */
        function cancelhovers() {
            if (!this.querySelector('.description.active')) return;
            const descriptions = this.querySelectorAll('.description.active');
            descriptions.forEach(function (el) {
                el.classList.remove('active');
            });
        }

        table.addEventListener('touchstart', cancelhovers, {passive: true});
        document.addEventListener('scroll', cancelhovers, {passive: true});

        /* startup the select lists in the table headers */
        const select_lists = [];
        const column_width = 100 / (table_data.show_columns + 1);
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
                th.style.width = `${column_width}%`;
            } else {
                console.error(`.select.index${i} missing from table.`);
            }
        }
        table_data.select_lists = select_lists;

        /* scroll indicator arrows */
        function scrollTo(x, y, element) {
            if (!element) element = window;
            if ('scrollBehavior' in document.documentElement.style) {
                element.scrollTo({
                    left: x,
                    top: y,
                    behavior: 'smooth'
                });
            } else {
                element.scrollTo(x, y);
            }
        }

        function arrows() {
            const figures = document.querySelectorAll('.wp-block-table.ruigehond014');
            const len = figures.length;
            for (let i = 0; i < len; ++i) {
                const figure = figures[i];
                figure.addEventListener('scroll', cancelhovers, {passive: true});

                let buttonLeft = figure.querySelector('.button.left');
                let buttonRight = figure.querySelector('.button.right');
                if (figure.scrollWidth > figure.clientWidth) {
                    if (!buttonLeft) {
                        buttonLeft = document.createElement('div');
                        buttonLeft.classList.add('button');
                        buttonLeft.classList.add('left');
                        buttonLeft.addEventListener('click', function () {
                            scrollTo(-1 * figure.clientWidth + figure.scrollLeft, 0, figure)
                        });
                        figure.appendChild(buttonLeft);
                    }
                    if ((figure.scrollLeft - 1) > 0) {
                        buttonLeft.classList.add('active');
                    } else {
                        buttonLeft.classList.remove('active');
                    }
                    if (!buttonRight) {
                        buttonRight = document.createElement('div');
                        buttonRight.classList.add('button');
                        buttonRight.classList.add('right');
                        buttonRight.addEventListener('click', function () {
                            scrollTo(figure.scrollLeft + figure.clientWidth, 0, figure);
                        });
                        figure.appendChild(buttonRight);
                    }
                    if (figure.scrollLeft + 1 < figure.scrollWidth - figure.clientWidth) {
                        buttonRight.classList.add('active');
                    } else {
                        buttonRight.classList.remove('active');
                    }
                    /* position buttons optimal top */
                    const half = window.innerHeight / 2;
                    const arrow_height = buttonRight.scrollHeight;
                    if (figure.scrollHeight > window.innerHeight) {
                        const rect = figure.getBoundingClientRect();
                        if (rect.bottom < half) {
                            // stick at the bottom
                            buttonLeft.classList.remove('halfway');
                            buttonRight.classList.remove('halfway');
                            buttonLeft.style.transform = 'translateY(-100%)';
                            buttonRight.style.transform = 'translateY(-100%)';
                        } else if (rect.top > half - arrow_height) {
                            // stick at the top
                            const height = figure.scrollHeight;
                            buttonLeft.classList.remove('halfway');
                            buttonRight.classList.remove('halfway');
                            buttonLeft.style.transform = `translateY(-${height}px)`;
                            buttonRight.style.transform = `translateY(-${height}px)`;
                        } else {
                            buttonLeft.classList.add('halfway');
                            buttonRight.classList.add('halfway');
                            buttonLeft.style.transform = 'translateY(-100%)';
                            buttonRight.style.transform = 'translateY(-100%)';
                        }
                    } else {
                        const height = figure.scrollHeight / 2;
                        buttonLeft.classList.remove('halfway');
                        buttonRight.classList.remove('halfway');
                        buttonLeft.style.transform = `translateY(-${height}px)`;
                        buttonRight.style.transform = `translateY(-${height}px)`;
                    }
                } else {
                    buttonLeft && buttonLeft.remove();
                    buttonRight && buttonRight.remove();
                }
                figure.addEventListener('scroll', arrows, {passive: true});
            }
        }

        window.addEventListener('resize', arrows, {passive: true});
        window.addEventListener('scroll', arrows, {passive: true});
        arrows();
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
    option.setAttribute('hidden', 'hidden');
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
