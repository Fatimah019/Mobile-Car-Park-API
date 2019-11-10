const updateAccount = () => {
    toastr.options.preventDuplicates = true;
    toastr.options.timeOut = 0;
    const url = routes.user();

    const fname = document.forms['settings_account']['fname'].value;
    const lname = document.forms['settings_account']['lname'].value;
    const email = document.forms['settings_account']['email'].value;
    const data = {
        first_name: fname,
        last_name: lname,
        email: email,
    };
    makePutRequest(url, data)
        .then(response => {
            handleResponse(response, 'Account');
        })
        .catch(error => {
            handleError(error);
        })
};


const makePutRequest = (url, data) => {
    axios.defaults.headers.common['Authorization'] = bearerToken;
    axios.defaults.headers.post['Content-Type'] = 'application/json';
    axios.defaults.headers.post['Accept'] = 'application/json';

    return axios.put(url, data);
};

let handleError = error => {
    let response  = error.response.data;
    let msg = '';

    if (error.response.status == '422' || response.hasOwnProperty('errors')) {
        $.each(error.response.data.errors, function (index, item) {
            msg += `<li> ${item[0]} </li>`;
        });
    } else {
        msg = error.response.data.message || error.toString();
    }
    toastr.error( `<p style="font-size:17px;">${msg}</p>`);
}


const authHeaders = () => {
    let token = localStorage.getItem('token');

    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': token
    }
};

const changePassword = () => {
    passwordPutRequest();
};

let handleResponse = (response, text) => {
    toastr.success( `${text} Updated`);
    if (text === 'Account') {
        localStorage.setItem('user', JSON.stringify(response.data.data.user));
    }
};

const passwordPutRequest = () => {
    const url = routes.changePassword();
    const { value: data } = Swal.fire({
        title: 'Change Password',
        html:
            '<input id="old_password" type="password" class="swal2-input" placeholder="Old Password">' +
            '<input id="new_password" type="password" class="swal2-input" placeholder="New Password">' +
            '<input id="new_password_confirmation" type="password" class="swal2-input" placeholder="Confirm New password">',
        focusConfirm: false,
        preConfirm: () => {
            return [
                document.getElementById('old_password').value,
                document.getElementById('new_password').value,
                document.getElementById('new_password_confirmation').value
            ]
        }
    }).then(values => {
        const data = values.value;
        const old_password = data[0];
        const new_password = data[1];
        const new_password_confirmation = data[2];
        const pass = {
            old_password: old_password,
            new_password: new_password,
            new_password_confirmation: new_password_confirmation
        };

        makePutRequest(url, pass).then( response => {
            handleResponse(response, 'Password');
        }).catch(error => {
            handleError(error);
        })
    })
}