(function (window) {
    'use strict';

    if (window.ExchangeTimer) return;

    window.ExchangeTimer = function( selector ) {
        this.elem = document.querySelector( selector );
        this.d = new Date(0, 0, 0, 0, 0, 0, 0, 0);
        this.timer = 'not init. interval';
        this.del = ' : ';
    }

    window.ExchangeTimer.prototype = {

        addLead: function(num) {
            var num = num + "";
            return (num.length < 2) ? "0" + num : num;
        },

        start: function() {
            var self = this;
            this.timer = setInterval(function() {
                self.d.setSeconds(self.d.getSeconds() + 1);
                self.elem.innerHTML = self.addLead( self.d.getHours() ) + self.del + self.addLead( self.d.getMinutes() ) + self.del + self.addLead( self.d.getSeconds() );
            }, 1000);
        },

        stop: function() {
            if( 'string' != typeof(this.timer) ) clearInterval( this.timer );
        }

    };
})(window);
