import { DEPENDENCIES } from './CONST';
import Settings from './Settings';
import injectChatIcon from './ChatIconInjector';
import { addChatSubmitButton } from './ChatSubmitButtonInjector';
import { IJID } from '@jsxc/jsxc/src/JID.interface';
import defaultAvatar from './DefaultAvatar';
import Storage from './Storage';
import { armXMPPSchemaHandler } from './XMPPSchemaHandler';

export default class Bootstrap {
   private jsxc;

   public check() {
      this.checkDependencies();
      this.checkFrame();
      this.checkSpecialPage();
   }

   private checkDependencies() {
      for (let dependency of DEPENDENCIES) {
         if (typeof (<any> window)[dependency] === 'undefined') {
            throw new Error(`Dependency "${dependency}" is missing.`);
         }
      }
   }

   private checkFrame() {
      if (window.parent && window !== window.parent) {
         throw new Error(`Abort, because we are running inside a frame.`);
      }
   }

   private checkSpecialPage() {
      if (/^(\/index.php)?\/s\//.test(window.location.pathname)) {
         throw new Error(`Abort, because we dont want to start chat on public shares.`);
      }

      if (OC.generateUrl('login/flow') === window.location.pathname) {
         throw new Error(`Abort, because chat is not needed on flow login.`);
      }

      if (OC.generateUrl('login/challenge/totp') === window.location.pathname) {
         throw new Error('Abort, because chat is not needed on totp page.');
      }
   }

   public start() {
      if (typeof OJSXC_CONFIG === 'undefined') {
         setTimeout(this.start.bind(this), 100);

         return;
      }

      JSXC.jQuery(document).on('ajaxSend', function(_elm, xhr, settings) {
         if (settings.crossDomain === false) {
            xhr.setRequestHeader('requesttoken', OC.requestToken);
            xhr.setRequestHeader('OCS-APIREQUEST', 'true');
         }
      });

      this.initJSXC();

      if (OC.generateUrl('/login') === window.location.pathname) {
         this.addWatcher();
         this.addAlternativeLogin();
      }

      injectChatIcon(this.jsxc.toggleRoster);
      armXMPPSchemaHandler(this.jsxc.executeXMPPUri);
   }

   private initJSXC() {
      this.jsxc = new JSXC({
         appName: 'Nextcloud',
         rosterVisibility: OJSXC_CONFIG.startMinimized ? 'hidden' : 'shown',
         loadConnectionOptions: Settings.loadConnection,
         loadOptions: Settings.load,
         onOptionChange: Settings.onOptionChange,
         avatarPlaceholder: (element: JQuery, name: string, color: string, jid: IJID) => {
            defaultAvatar(element, name, jid);
         },
         onUserRequestsToGoOnline: this.onUserRequestsToGoOnline.bind(this),
         RTCPeerConfig: {
            url: OC.generateUrl('apps/ojsxc/settings/iceServers')
         },
         automaticallyRestoreAccounts: window.location.pathname.indexOf(OC.generateUrl('/login')) !== 0,
      });

      //For debugging
      (<any> window).ojsxc = {
         jsxc: this.jsxc,
      };

      if (this.jsxc.numberOfCachedAccounts === 0) {
         if (OC.getCurrentUser().uid) {
            this.jsxc.start();
         }

         if (OJSXC_CONFIG.serverType === 'internal') {
            let storage = Storage.get();
            let jid = storage.getItem('internal:jid');
            let url = storage.getItem('internal:url');

            if (jid && url) {
               console.log('Start connection to internal XMPP server');

               this.jsxc.start(url, jid, 'sid', '1234');

               storage.removeItem('internal:jid');
               storage.removeItem('internal:url');
            }
         }
      }
   }

   private addWatcher() {
      let formElement = $('#body-login form[name="login"]');
      let usernameElement = $('#user');
      let passwordElement = $('#password');

      if (formElement.length && usernameElement.length && passwordElement.length) {
         this.jsxc.watchForm(formElement, usernameElement, passwordElement);
      }

      let logoutElement = $('[data-id="logout"] a');

      if (logoutElement.length) {
         this.jsxc.watchLogoutClick(logoutElement);
      }
   }

   private addAlternativeLogin() {
      let formElement = $('#body-login form[name="login"]');

      addChatSubmitButton(formElement, this.jsxc.translate);
   }

   private async onUserRequestsToGoOnline() {
      try {
         let settings = await Settings.loadConnection(undefined, undefined);

         if (!Storage.get().getItem('serverIsOmniscient') && OJSXC_CONFIG.serverType !== 'internal') {
            this.jsxc.showLoginBox();

            return;
         }

         if (!settings) {
            throw new Error('No settings provided');
         }

         let xmpp = settings.xmpp;
         let jid = xmpp.node + '@' + xmpp.domain;

         if (xmpp.resource) {
            jid +=  '/' + xmpp.resource;
         }

         if (OJSXC_CONFIG.serverType === 'internal') {
            this.jsxc.start(xmpp.url, jid, 'sid', '1234');
         } else {
            this.jsxc.start(xmpp.url, jid, xmpp.password || '');
         }
      } catch (err) {
         console.log('Error during log in', err);

         this.jsxc.showLoginBox();
      }
   }
}
