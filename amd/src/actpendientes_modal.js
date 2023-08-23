import ModalFactory from 'core/modal_factory';
import Templates from 'core/templates';

export const init = async (activities, selector) => {

    let actpendcard = document.getElementById(selector);
    if (actpendcard) {
        actpendcard.onclick = async function() {
            const modal =  await ModalFactory.create({
                title: 'Actividades pendientes',
                type: ModalFactory.types.CANCEL,
                body: Templates.render('local_uvirtual/actpendiente_tabla_modal', {activities: activities}),
            });
            modal.setButtonText('cancel', 'Cerrar');
            modal.show();
        };
    }

};