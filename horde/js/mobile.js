/**
 * Base logic for all jQuery Mobile applications.
 *
 * Copyright 2010-2012 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Michael J. Rubinsky <mrubinsk@horde.org>
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Horde
 */
var HordeMobile = {

    notify_handler: function() { return HordeMobile.showNotifications; },

    serverError: 0,

    /**
     * Common URLs.
     *
     * Required properties to be set from calling applications:
     * - ajax: AJAX endpoint.
     */
    urls: {},

    debug: function(label, e)
    {
        if (!HordeMobile.is_logout && window.console && window.console.error) {
            window.console.error(label, jQuery.browser.mozilla ? e : jQuery.makeArray(e));
        }
    },

    /**
     * Perform an Ajax action
     *
     * @param string action      The AJAX request method.
     * @param object params      The parameter hash for the AJAX request.
     * @param function callback  A callback function for successful request.
     * @param object opts        Additional options for jQuery.ajax() (since
     *                           Horde 4.1).
     */
    doAction: function(action, params, callback, opts)
    {
        $.mobile.showPageLoadingMsg();
        var options = $.extend(
            {
                'url': HordeMobile.urls.ajax + action,
                'data': params,
                'error': HordeMobile.errorCallback,
                'success': function(d, t, x) { HordeMobile.doActionComplete(d, callback); },
                'type': 'post'
            },
            opts || {});
        $.ajax(options);
    },

    doActionComplete: function(d, callback)
    {
        HordeMobile.inAjaxCallback = true;
        var r = d.response;
        if (r && $.isFunction(callback)) {
            try {
                callback(r);
            } catch (e) {
                HordeMobile.debug('doActionComplete', e);
            }
        }

        HordeMobile.server_error = 0;
        HordeMobile.notify_handler(d.msgs || []);
        HordeMobile.inAjaxCallback = false;
        $.mobile.hidePageLoadingMsg();
    },

    showNotifications: function(msgs)
    {
        if (!msgs.length || HordeMobile.is_logout) {
            return;
        }

        var list = $('#horde-notification'), li;
        list.html('');

        $.each(msgs, function(key, m) {
            switch (m.type) {
            case 'horde.ajaxtimeout':
                HordeMobile.logout(m.message);
                return false;

            case 'horde.error':
            case 'horde.warning':
            case 'horde.message':
            case 'horde.success':
                li = $('<li class="' + m.type.replace('.', '-') + '">');
                if (m.flags && $.inArray('content.raw', m.flags) != -1) {
                    // TODO: This needs some fixing:
                    li.html(m.message.replace('<a href=', '<a rel="external" href='));
                } else {
                    li.text(m.message);
                }
                list.append(li);
                break;
            }
        });
        if (list.html()) {
            $.mobile.changePage($('#notification'), { transition: 'pop' });
        }
    },

    logout: function(url)
    {
        HordeMobile.is_logout = true;
        window.location = (url || HordeMobile.urls.ajax + 'logOut');
    },

    errorCallback: function(x, t, e)
    {

    },

    onDocumentReady: function()
    {
        // Global ajax options.
        $.ajaxSetup({
            dataFilter: function(data, type)
            {
                // Remove json security token
                filter = /^\/\*-secure-([\s\S]*)\*\/s*$/;
                return data.replace(filter, "$1");
            }
        });
        $('#notification').live('pagebeforeshow', function() { $('#horde-notification').listview('refresh'); });
    }
};
$(HordeMobile.onDocumentReady);
