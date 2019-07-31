jQuery(document).ready(function ($) {
    'use strict';

    // /** @var int */
    // var fullSteps;

    // var step = 0;

    /**
     * @var @global ml2e {
     *      exchange_url: server/exchange/
     *      files: exists filenames array
     *      productsCount: counts for calculate full path
     *      OffersCount:   counts for calculate full path
     *      debug_only: bool
     * }
     */

    if (window.ExhangeProgress) return;

    window.ExhangeProgress = function (args) {
        this.error = false;
        this.request = null;

        this.currentFilename = -1;
        this.filenames = ml2e.files;

        this.currentStep = -1;
        this.steps = [
            // 'checkauth',
            'init',
            // 'file',
            'import',
            // 'deactivate',
            // 'complete',
        ];

        this.$report = args.$report || $();
        this.$progress = args.$progress || $();

        if ('function' !== typeof args.onError) {
            console.error('onError must be function');
        } else {
            this.onError = args.onError;
        }

        if ('function' !== typeof args.onEnd) {
            console.error('onError must be function');
        } else {
            this.onEnd = args.onEnd;
        }
    }

    window.ExhangeProgress.prototype = {
        onError: function () {
            console.error('We have a error!');
        },
        onEnd: function () {
            console.log('Exchange is end!');
        },

        __getRequest: function (data, func) {
            var self = this;

            data.action = '1c4wp_exchange';
            data.exchange_nonce = ml2e.nonce;

            if (this.error) return;

            return $.ajax({
                url: ml2e.ajax_url,
                type: 'GET',
                data: data,

                error: function (response) {
                    self.setError();
                },

                success: function (response) {
                    /**
                     * Prepare response message
                     * @type [array]
                     */
                    var answer = response.split('\n'),
                        result = answer[0].toLowerCase().trim(), // (success|progress|failure),
                        message = answer[1],
                        extended = answer[2] || '';
                    // , mysql    = answer[3] || '';

                    if (0 === result.indexOf('zip')) {
                        if ('yes' !== result.split('=')[1]) {
                            self.setError('Zip extension is required!');
                            return false;
                        }

                        /** Change file_limit to.. */
                        message = 'Exchange is inited.';
                        result = 'success';
                    } else if (0 !== result.indexOf('success') && 0 !== result.indexOf('progress')) {
                        /** send response to report too */
                        self.setError(response);
                        // Frontend protection ;D
                        return false;
                    }

                    var currentStep = self.getCurrentStep();

                    self.setProgress(currentStep);
                    self.addReport(message);

                    if ('success' == result) {
                        self.currentFilename++;

                        if (self.currentFilename && self.getCurrentFilename()) {
                            self.__exchange(currentStep);
                        } else {
                            // @note set next iteration
                            var nextStep = self.getNextStep();
                            if (nextStep) {
                                self.__exchange(nextStep);
                            } else {
                                self.setProgress(100);
                                // self.addReport( 'Выгрузка успешно завершена.' );
                                self.onEnd();
                            }
                        }
                    } else if ('progress' === result) {
                        self.__exchange(currentStep);
                    } else {
                        console.log('something wrong, result: ', result);
                    }
                }
            });
        },

        __exchange: function (step) {
            if ('function' !== typeof this[step]) {
                console.error('Step "' + step + '" not exists');
                return false;
            }

            this[step]();
        },

        // add mesage to textarea
        addReport: function (msg, extMsg) {
            if (!msg) return;

            if (extMsg) {
                msg += '(' + extMsg + ')';
            }

            this.$report.append(msg + '\n');
            // this.$report.scrollTop( this.$report[0].scrollHeight );
        },

        setError: function (msg, extMsg) { // response
            this.error = {
                msg: msg || 'Случилась непредвиденая ошибка, попробуйте повторить позже',
                extMsg: extMsg || '',
            }

            /**
             * Stop procedure
             */
            this.request.abort();

            /**
             * Stay error message to textarea
             */
            this.addReport(msg, extMsg);

            /**
             * Fill error color
             */
            this.$progress.css('background', '#F00'); // '#ED3752'

            this.onError();
        },

        setProgress: function (int) {
            /**
             * Fill success color
             */
            if (100 == int) {
                this.$progress.css('background', '#14B278');
                this.$progress.css('width', '100%');
                this.$progress.css('max-width', '100%');
                return;
            } else {
                /**
                 * @todo fixit!
                 */
                var width = parseFloat(this.$progress.width()) || 0;
                this.$progress.css('width', (100 / (this.$progress.parent().width() / width)) + 3 + '%');
                this.$progress.css('max-width', '99%');
            }
        },

        getCurrentFilename: function () {
            return this.filenames[this.currentFilename];
        },

        getCurrentStep: function () {
            return this.steps[this.currentStep];
        },

        getNextStep: function () {
            this.currentStep++;
            return this.getCurrentStep();
        },

        checkauth: function () {
            console.log('for what?');
            // this.request = this.__getRequest({
            //     'type': 'catalog',
            //     'mode': 'checkauth'
            // }, this.checkauth);
        },

        init: function () {
            this.request = this.__getRequest({
                'type': 'catalog',
                'mode': 'init',
                'version': this.filenames.length > 4 ? '3.1' : '',
            }, this.init);
        },

        file: function () {
            console.log('for what?');
            // this.request = this.__getRequest({
            //     'type': 'catalog',
            //     'mode': 'file',
            //     filename: filename
            // }, this.file);
        },

        import: function () {
            var filename = this.getCurrentFilename();
            if (filename) {
                this.request = this.__getRequest({
                    'type': 'catalog',
                    'mode': 'import',
                    filename: this.getCurrentFilename()
                }, this.import);
            } else {
                this.setError('Проверьте папку /wp-content/uploads/1c-exchange/catalog возможно она пуста.');
            }
        },

        deactivate: function () {
            this.request = this.__getRequest({
                'type': 'catalog',
                'mode': 'deactivate'
            }, this.deactivate);
        },

        complete: function () {
            this.request = this.__getRequest({
                'type': 'catalog',
                'mode': 'complete'
            }, this.complete);
        },

        start: function () {
            this.__exchange(this.getNextStep());
        },
    }
});
